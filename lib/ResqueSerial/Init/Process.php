<?php


namespace ResqueSerial\Init;


use Exception;
use Resque;
use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\QueueLock;
use ResqueSerial\Serial\QueueImage;
use ResqueSerial\Serial\SerialWorker;
use ResqueSerial\Serial\SerialWorkerImage;
use ResqueSerial\Worker;
use ResqueSerial\WorkerImage;

class Process {

    private $stopping = false;

    private $logger;

    /** @var GlobalConfig */
    private $globalConfig;

    public function __construct() {
        $this->logger = Log::main();
    }

    public function maintain() {
        $this->updateProcLine("maintaining");
        while (true) {
            $this->recover();
            sleep(5);
            pcntl_signal_dispatch();
            if ($this->stopping) {
                break;
            }
        }
    }

    public function recover() {
        $this->logger->debug("========= Starting maintenance");

        foreach ($this->globalConfig->getQueueList() as $queue) {
            $workerConfig = $this->globalConfig->getWorkerConfig($queue);

            if ($workerConfig == null) {
                $this->logger->error("Invalid worker config for queue $queue");
                continue;
            }

            $this->logger->debug("====== Starting maintenance of queue $queue");

            $blocking = $workerConfig->getBlocking();
            $maxSerialWorkers = $workerConfig->getMaxSerialWorkers();

            $livingWorkerCount = $this->cleanupWorkers($queue);
            $this->logger->debug("Living worker count: $livingWorkerCount");
            $toCreate = $workerConfig->getWorkerCount() - $livingWorkerCount;
            $this->logger->debug("Need to create $toCreate workers");

            $orphanedSerialWorkers = $this->getOrphanedSerialWorkers($queue);
            $this->logger->debug("Orphaned serial workers: " . json_encode($orphanedSerialWorkers));
            $orphanedSerialQueues = $this->getOrphanedSerialQueues($queue);
            $this->logger->debug("Orphaned serial queues: " . json_encode($orphanedSerialWorkers));

            for ($i = 0; $i < $toCreate; ++$i) {
                try {
                    $this->logger->debug("=== Creating worker [$i]");
                    $workersToAppend = @$orphanedSerialWorkers[0];
                    $orphanedSerialWorkers = array_slice($orphanedSerialWorkers, 1);
                    $this->logger->debug("Orphaned serial workers to append: " . json_encode($workersToAppend));

                    $spaceForSerialWorkers = $maxSerialWorkers - count($workersToAppend);
                    $this->logger->debug("Space left to start fresh serial workers: $spaceForSerialWorkers");
                    $serialQueuesToStart = array_slice($orphanedSerialQueues, 0, $spaceForSerialWorkers);
                    $orphanedSerialQueues = array_slice($orphanedSerialQueues, $spaceForSerialWorkers);

                    $this->logger->debug("Serial queues to create workers for: " . json_encode($serialQueuesToStart));

                    $pid = Resque::fork();
                    if ($pid === false) {
                        throw new Exception('Fork returned false.');
                    }

                    if (!$pid) {
                        $worker = new Worker(explode(',', $queue), $this->globalConfig);
                        $worker->setLogger(Log::prefix(getmypid() . "-worker-$queue"));

                        if ($workersToAppend) {
                            foreach ($workersToAppend as $toAppend) {
                                $this->assignSerialWorker($worker, $toAppend);
                            }
                        }

                        foreach ($serialQueuesToStart as $queueToStart) {
                            $this->startSerialQueue($worker->getImage(), $queueToStart);
                        }

                        $this->logger->notice("Starting worker $worker", array('worker' => $worker));
                        $worker->work(Resque::DEFAULT_INTERVAL, $blocking);
                        exit();
                    }
                } catch (Exception $e) {
                    $this->logger->emergency("Could not fork worker $i for queue $queue", ['exception' => $e]);
                    die();
                }
            }

            $this->logger->debug("====== Finished maintenance of queue $queue");

            // check interruption
            pcntl_signal_dispatch();
            if ($this->stopping) {
                $this->logger->debug("========= Received stop signal, ending...");
                return;
            }
        }
        $this->logger->debug("========= Maintenance ended");
    }

    public function reload() {
        GlobalConfig::reload();
        $this->globalConfig = $config = GlobalConfig::instance();
        Log::initFromConfig($config);
        $this->logger = Log::prefix('init-process');

        $this->signalWorkers(SIGHUP, "HUP");
        $this->signalSerialWorkers(SIGHUP, "HUP");
    }

    /**
     * send TERM to all workers and serial workers
     */
    public function shutdown() {
        $this->stopping = true;

        $this->signalWorkers(SIGTERM, "TERM");
        $this->signalSerialWorkers(SIGTERM, "TERM");
    }

    public function start() {
        $this->updateProcLine("starting");
        $this->initialize();
        $this->recover();
    }

    public function updateProcLine($status) {
        $processTitle = "resque-serial-init: $status";
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        } else if (function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    /**
     * Assigns serial worker to specified parent worker.
     *
     * @param Worker $parent
     * @param $toAppend
     */
    private function assignSerialWorker(Worker $parent, $toAppend) {
        $image = SerialWorkerImage::fromId($toAppend);

        if (!$image->exists()) {
            return; // ended before we got to it
        }

        $oldParent = WorkerImage::fromId($image->getParent());
        $oldParent->removeSerialWorker($image->getId());

        $newParent = WorkerImage::fromId((string)$parent);
        $newParent->addSerialWorker($image->getId());
        $image->setParent($newParent->getId());
    }

    /**
     * Removes dead workers from queue pool and counts the number of living ones.
     *
     * @param string $queue
     *
     * @return int number of living workers on specified queue
     */
    private function cleanupWorkers($queue) {
        $workers = Resque::redis()->smembers(Key::workers());

        $livingWorkers = 0;

        foreach ($workers as $workerId) {
            $image = WorkerImage::fromId($workerId);

            if ($queue != $image->getQueue()) {
                continue; // not this queue
            }
            if (!$image->isLocal()) {
                continue; // not this machine
            }

            if (!$image->isAlive()) {
                // cleanup
                WorkerImage::fromId($workerId)
                        ->removeFromPool()
                        ->clearState()
                        ->clearStarted()
                        ->clearSerialWorkers();
            } else {
                $livingWorkers++;
            }

        }

        return $livingWorkers;
    }

    /**
     * Detects all orphaned serial queues derived from specified queue and returns them.
     * Orphaned serial queue is serial queue without running serial worker.
     *
     * @param string $queue
     *
     * @return string[] list of orphaned serial queue names
     */
    private function getOrphanedSerialQueues($queue) {
        $orphanedQueues = [];
        foreach (QueueImage::all() as $serialQueue) {
            $queueImage = QueueImage::fromName($serialQueue);

            if ($queueImage->getParentQueue() != $queue) {
                continue; // not our queue
            }

            if (QueueLock::exists($serialQueue)) {
                continue; // someone is holding the queue
            }

            $orphanedQueues[] = $serialQueue;
        }

        return $orphanedQueues;
    }

    /**
     * Detects all orphaned serial workers on specified queue and returns them.
     * Orphaned serial worker is running serial worker without running parent worker.
     *
     * @param string $queue
     *
     * @return string[][] map of dead parent worker ID to list of orphaned serial worker IDs
     * that used to have it as a parent
     */
    private function getOrphanedSerialWorkers($queue) {
        $orphanedGroups = [];
        foreach (SerialWorkerImage::all() as $serialWorkerId) {
            $workerImage = SerialWorkerImage::fromId($serialWorkerId);
            $queueImage = QueueImage::fromName($workerImage->getQueue());

            if ($queue != $queueImage->getParentQueue()) {
                continue; // not our queue
            }

            if (!$workerImage->isLocal()) {
                continue; // not our responsibility
            }

            $parent = $workerImage->getParent();

            if ($parent == '') {
                $orphanedGroups['_'][] = $workerImage->getId();
                continue;
            }

            $parentImage = WorkerImage::fromId($parent);

            if (!$parentImage->isAlive()) {
                $orphanedGroups[$parent][] = $workerImage->getId();
            }
        }

        return $orphanedGroups;
    }

    private function initialize() {
        $this->globalConfig = $config = GlobalConfig::instance();
        Resque::setBackend($config->getBackend());

        Log::initFromConfig($config);
        $this->logger = Log::prefix('init-process');

        $this->registerSigHandlers();
    }

    private function registerSigHandlers() {
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        pcntl_signal(SIGHUP, [$this, 'reload']);
        $this->logger->debug('Registered signals');
    }

    private function signalSerialWorkers($signal, $signalName) {
        $workers = WorkerImage::all();
        foreach ($workers as $worker) {
            $image = WorkerImage::fromId($worker);
            $this->logger->debug("Signalling $signalName " . $image->getId() . " " . $image->getPid());
            posix_kill($image->getPid(), $signal);
        }

    }

    private function signalWorkers($signal, $signalName) {
        $workers = WorkerImage::all();
        foreach ($workers as $worker) {
            $image = WorkerImage::fromId($worker);
            $this->logger->debug("Signalling $signalName " . $image->getId() . " " . $image->getPid());
            posix_kill($image->getPid(), $signal);
        }

    }

    /**
     * Starts new serial worker on specified serial queue and assigns specified worker as its parent.
     *
     * @param WorkerImage $parent
     * @param string $queueToStart
     */
    private function startSerialQueue(WorkerImage $parent, $queueToStart) {
        $lock = new QueueLock($queueToStart);
        if (!$lock->acquire()) {
            $this->logger->notice("Failed to acquire lock for serial queue $queueToStart.");

            return;
        }
        try {
            if (Resque::fork() === 0) {
                $serialWorker = new SerialWorker($queueToStart);
                $serialWorker->work($parent->getId());
                $lock->release();
                exit(0);
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to start serial worker on $queueToStart. Error: "
                    . $e->getMessage());
        }
    }
}