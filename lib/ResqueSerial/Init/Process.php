<?php


namespace ResqueSerial\Init;


use Exception;
use Resque;
use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\Serial\SerialWorkerImage;
use ResqueSerial\Worker;
use ResqueSerial\WorkerImage;

class Process {

    private $stopping = false;

    private $logger;

    public function __construct() {
        $this->logger = Log::main();
    }

    public function recover() {
        $this->updateProcLine("starting");
        $config = GlobalConfig::load();
        Resque::setBackend($config->getBackend());

        Log::initFromConfig($config);
        $this->logger = Log::prefix('init-process');

        $this->registerSigHandlers();

        foreach ($config->getQueueList() as $queue) {
            $workerConfig = $config->getWorkerConfig($queue);

            if ($workerConfig == null) {
                Log::main()->error("Invalid worker config for queue $queue");
                continue;
            }

            $blocking = $workerConfig->getBlocking();
            $maxSerialWorkers = $workerConfig->getMaxSerialWorkers();

            $livingWorkerCount = $this->cleanupWorkers($queue);
            $toCreate = $workerConfig->getWorkerCount() - $livingWorkerCount;

            $orphanedSerialWorkers = $this->getOrphanedSerialWorkers($queue);

            $orphanedSerialQueues = $this->getOrphanedSerialQueues($queue);
            for ($i = 0; $i < $toCreate; ++$i) {
                try {
                    $workersToAppend = @$orphanedSerialWorkers[0];
                    $orphanedSerialWorkers = array_slice($orphanedSerialWorkers, 1);

                    $spaceForSerialWorkers = $maxSerialWorkers - count($workersToAppend);
                    $serialQueuesToStart = array_slice($orphanedSerialQueues, 0, $spaceForSerialWorkers);
                    $orphanedSerialQueues = array_slice($orphanedSerialQueues, $spaceForSerialWorkers);

                    $pid = Resque::fork();
                    if ($pid === false) {
                        throw new Exception('Fork returned false.');
                    }

                    if (!$pid) {
                        $worker = new Worker(explode(',', $queue), $config);
                        $worker->setLogger(Log::prefix(getmypid() . "-worker-$queue"));

                        if ($workersToAppend) {
                            foreach ($workersToAppend as $toAppend) {
                                $this->appendSerialWorker($worker, $toAppend);
                            }
                        }

                        foreach ($serialQueuesToStart as $queueToStart) {
                            $this->startSerialQueue($worker, $queueToStart);
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

            // check interruption
            pcntl_signal_dispatch();
            if ($this->stopping) {
                return;
            }
        }
    }

    /**
     * send TERM to all workers and serial workers
     */
    public function shutdown() {
        $this->stopping = true;

        $workers = WorkerImage::all();
        foreach ($workers as $worker) {
            $image = WorkerImage::fromId($worker);
            $this->logger->debug("Killing " . $image->getId() . " " . $image->getPid());
            posix_kill($image->getPid(), SIGTERM);
        }

        $serialWorkers = SerialWorkerImage::all();
        foreach ($serialWorkers as $worker) {
            $image = SerialWorkerImage::fromId($worker);
            $this->logger->debug("Killing " . $image->getId() . " " . $image->getPid());
            posix_kill($image->getPid(), SIGTERM);
        }
    }

    public function updateProcLine($status) {
        $processTitle = "resque-serial-init: $status";
        if(function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        }
        else if(function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    public function wait() {
        $this->updateProcLine("waiting");
        while (true) {
            sleep(5);
            pcntl_signal_dispatch();
            if ($this->stopping) {
                break;
            }
        }
    }

    /**
     * @param Worker $parent
     * @param $toAppend
     */
    private function appendSerialWorker(Worker $parent, $toAppend) {
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

    private function cleanupWorkers($queue) {
        $workers = Resque::redis()->smembers(Key::workers());

        $livingWorkers = 0;

        foreach ($workers as $workerId) {
            $image = WorkerImage::fromId($workerId);

            if ($queue != $image->getQueue()) {
                continue; // not this queue
            }
            if (gethostname() != $image->getHostname()) {
                continue; // not this machine
            }

            $isAlive = posix_getpgid($image->getPid()) > 0;

            if (!$isAlive) {
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
     * @param string $queue
     *
     * @return string[]
     */
    private function getOrphanedSerialQueues($queue) {
        return []; // TODO
    }

    /**
     * @param string $queue
     *
     * @return string[][]
     */
    private function getOrphanedSerialWorkers($queue) {
        return []; // TODO
    }

    private function registerSigHandlers() {
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        $this->logger->debug('Registered signals');
    }

    /**
     * @param Worker $parent
     * @param string $queueToStart
     */
    private function startSerialQueue(Worker $parent, $queueToStart) {
        // TODO
    }
}