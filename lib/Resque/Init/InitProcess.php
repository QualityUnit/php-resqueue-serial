<?php


namespace Resque\Init;


use Exception;
use Resque;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Process;
use Resque\Queue\QueueLock;
use Resque\Queue\SerialQueueImage;
use Resque\Scheduler\Scheduler;
use Resque\SignalHandler;
use Resque\Worker\Serial\SerialWorker;
use Resque\Worker\Serial\SerialWorkerImage;
use Resque\Worker\StandardWorker;
use Resque\Worker\WorkerImage;

class InitProcess {

    private $stopping = false;
    private $reloaded = false;

    public function maintain() {
        Process::setTitle("maintaining");
        while (true) {
            sleep(5);
            SignalHandler::dispatch();
            if ($this->stopping) {
                break;
            }
            $this->recover();
        }
    }

    public function recover() {
        $this->reloaded = false; // refresh reload state
        Log::debug("========= Starting maintenance");

        $this->maintainScheduler();

        foreach (GlobalConfig::getInstance()->getQueueList() as $queue) {
            $workerConfig = GlobalConfig::getInstance()->getWorkerConfig($queue);

            if ($workerConfig == null) {
                Log::error("Invalid worker config for queue $queue");
                continue;
            }

            Log::debug("====== Starting maintenance of queue $queue");

            $livingWorkerCount = $this->cleanupWorkers($queue);
            Log::debug("Living worker count: $livingWorkerCount");
            $toCreate = $workerConfig->getWorkerCount() - $livingWorkerCount;
            Log::debug("Need to create $toCreate workers");

            // create missing standard workers
            for ($i = 0; $i < $toCreate; ++$i) {
                try {
                    Log::debug("=== Creating worker [$i]");

                    $pid = Process::fork();
                    if ($pid === false) {
                        throw new Exception('Fork returned false.');
                    }
                } catch (Exception $e) {
                    Log::emergency("Could not fork worker $i for queue $queue", ['exception' => $e]);
                    exit(1);
                }

                if (!$pid) {
                    $this->unregisterSigHandlers(); // do not keep handlers from main process in child
                    try {
                        $worker = new StandardWorker($queue);
                        Log::notice("Starting worker {$worker->getImage()->getId()}");
                        $worker->work();
                        exit();
                    } catch (Exception $e) {
                        Log::error("Worker " . posix_getpid()
                                . " failed unexpectedly.", ['exception' => $e]);
                    }
                    exit(1);
                }
            }

            // sort out serial workers
            $maxSerialWorkers = $workerConfig->getMaxSerialWorkers();

            // append orphaned serial workers
            $orphanedSerialWorkers = $this->getOrphanedSerialWorkers($queue);
            Log::debug("Orphaned serial workers: " . json_encode($orphanedSerialWorkers));

            foreach (WorkerImage::all() as $workerId) {
                $workerImage = WorkerImage::fromId($workerId);
                if ($workerImage->getQueue() != $queue) {
                    continue;
                }

                while (count($workerImage->getSerialWorkers()) < $maxSerialWorkers) {
                    $workerToAppend = array_pop($orphanedSerialWorkers);
                    if (!$workerToAppend) {
                        break;
                    }
                    Log::debug("Appending serial worker $workerToAppend to $workerId");

                    $serialImage = SerialWorkerImage::fromId($workerToAppend);
                    $this->assignSerialWorker($workerImage, $serialImage);
                }

                if (count($orphanedSerialWorkers) == 0) {
                    break;
                }
            }
            Log::debug("Orphaned serial workers left: " . json_encode($orphanedSerialWorkers));

            // create serial workers for orphaned queues and append them
            $orphanedSerialQueues = $this->getOrphanedSerialQueues($queue);
            Log::debug("Orphaned serial queues: " . json_encode($orphanedSerialQueues));

            foreach (WorkerImage::all() as $workerId) {
                $workerImage = WorkerImage::fromId($workerId);
                if ($workerImage->getQueue() != $queue) {
                    continue;
                }

                while (count($workerImage->getSerialWorkers()) < $maxSerialWorkers) {
                    $queueToStart = array_pop($orphanedSerialQueues);
                    if (!$queueToStart) {
                        break;
                    }

                    Log::debug("Starting new serial queue $queueToStart on worker $workerId");

                    $this->startSerialQueue($workerImage, $queueToStart);
                }
            }
            Log::debug("Orphaned serial queues left: " . json_encode($orphanedSerialQueues));

            Log::debug("====== Finished maintenance of queue $queue");

            // check interruption
            SignalHandler::dispatch();
            if ($this->stopping || $this->reloaded) {
                Log::debug("========= Received stop or reload signal, halting maintenance...");

                return;
            }
        }
        Log::debug("========= Maintenance ended");
    }

    public function reload() {
        Log::debug("Reloading configuration");
        GlobalConfig::reload();
        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix('init-process'));
        $this->reloaded = true;

        $this->signalWorkers(SIGHUP, "HUP");
        $this->signalSerialWorkers(SIGHUP, "HUP");
        $this->signalScheduler(SIGHUP, "HUP");
    }

    /**
     * send TERM to all workers and serial workers
     */
    public function shutdown() {
        $this->stopping = true;

        $this->signalWorkers(SIGTERM, "TERM");
        $this->signalSerialWorkers(SIGTERM, "TERM");
        $this->signalScheduler(SIGTERM, "TERM");
    }

    public function start() {
        Process::setTitlePrefix("init");
        Process::setTitle("starting");
        $this->initialize();
        $this->recover();
    }

    /**
     * Assigns serial worker to specified parent worker.
     *
     * @param WorkerImage $newParent
     * @param SerialWorkerImage $toAppend
     */
    private function assignSerialWorker(WorkerImage $newParent, SerialWorkerImage $toAppend) {
        if (!$toAppend->exists()) {
            return; // ended before we got to it
        }

        $oldParent = WorkerImage::fromId($toAppend->getParent());
        $oldParent->removeSerialWorker($toAppend->getId());

        $newParent->addSerialWorker($toAppend->getId());
        $toAppend->setParent($newParent->getId());
    }

    /**
     * Removes dead workers from queue pool and counts the number of living ones.
     *
     * @param string $queue
     *
     * @return int number of living workers on specified queue
     */
    private function cleanupWorkers($queue) {
        Log::debug("Worker cleanup started");

        $totalWorkers = 0;
        $livingWorkers = 0;

        foreach (WorkerImage::all() as $workerId) {
            $image = WorkerImage::fromId($workerId);

            if ($queue != $image->getQueue()) {
                continue; // not this queue
            }
            if (!$image->isLocal()) {
                continue; // not this machine
            }

            if (!$image->isAlive()) {
                Log::debug("Cleaning up dead worker $workerId");
                // cleanup
                WorkerImage::fromId($workerId)
                        ->removeFromPool()
                        ->clearState()
                        ->clearStarted()
                        ->clearSerialWorkers();
            } else {
                $livingWorkers++;
            }
            $totalWorkers++;
        }

        Log::debug("Worker cleanup done, processed $totalWorkers workers");

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
        Log::debug("Getting orphaned serial queues");
        $orphanedQueues = [];
        foreach (SerialQueueImage::all() as $serialQueue) {
            if ($serialQueue == "") {
                Log::notice("Empty serial queue in orphan check");
                continue;
            }

            $queueImage = SerialQueueImage::fromName($serialQueue);

            if ($queueImage->getParentQueue() != $queue) {
                Log::debug("Parent queue doesn't match: $serialQueue");
                continue; // not our queue
            }

            if (QueueLock::exists($serialQueue)) {
                Log::debug("Lock exists for $serialQueue");
                continue; // someone is holding the queue
            }

            Log::debug("Orphaned queue found: $serialQueue");
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
     * @return string[] list of orphaned serial worker IDs
     */
    private function getOrphanedSerialWorkers($queue) {
        Log::debug("Getting orphaned serial workers");
        $orphanedGroups = [];
        foreach (SerialWorkerImage::all() as $serialWorkerId) {
            $workerImage = SerialWorkerImage::fromId($serialWorkerId);
            $queueImage = SerialQueueImage::fromName($workerImage->getQueue());

            if ($queue != $queueImage->getParentQueue()) {
                continue; // not our queue
            }

            if (!$workerImage->isLocal()) {
                continue; // not our responsibility
            }

            $parent = $workerImage->getParent();

            if (!$workerImage->isAlive()) {
                Log::debug("Cleaning up dead serial worker $serialWorkerId");

                $workerImage
                        ->removeFromPool()
                        ->clearState()
                        ->clearParent()
                        ->clearStarted();

                $parentImage = WorkerImage::fromId($parent);
                if ($parent != '' && $parentImage->isAlive()) {
                    $parentImage->removeSerialWorker($workerImage->getId());
                }
                continue;
            }

            if ($parent == '') {
                Log::debug("Orphaned worker with unknown parent: $serialWorkerId");
                $orphanedGroups[] = $workerImage->getId();
                continue;
            }

            $parentImage = WorkerImage::fromId($parent);

            if (!$parentImage->isAlive()) {
                Log::debug("Orphaned worker with dead parent: $serialWorkerId");
                $orphanedGroups[] = $workerImage->getId();
            }
        }

        return $orphanedGroups;
    }

    private function initialize() {
        Resque::setBackend(GlobalConfig::getInstance()->getBackend());

        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix('init-process'));

        $this->registerSigHandlers();
    }

    private function maintainScheduler() {
        $pid = Scheduler::getLocalPid();
        if ($pid && posix_getpgid($pid) > 0) {
            return;
        }

        $pid = Process::fork();
        if ($pid === false) {
            throw new Exception('Fork returned false.');
        }

        if ($pid == 0) {
            $worker = new Scheduler();
            $worker->work();
            exit(0);
        }
    }

    private function registerSigHandlers() {
        SignalHandler::instance()->unregisterAll()
                ->register(SIGTERM, [$this, 'shutdown'])
                ->register(SIGINT, [$this, 'shutdown'])
                ->register(SIGQUIT, [$this, 'shutdown'])
                ->register(SIGHUP, [$this, 'reload'])
                ->register(SIGCHLD, SIG_IGN); // prevent zombie children by ignoring them
        Log::debug('Registered signals');
    }

    private function signalScheduler($signal, $signalName) {
        $pid = Scheduler::getLocalPid();
        Log::debug("Signalling $signalName to scheduler $pid");
        posix_kill($pid, $signal);
    }

    private function signalSerialWorkers($signal, $signalName) {
        $serialWorkers = SerialWorkerImage::all();
        foreach ($serialWorkers as $serialWorker) {
            $image = SerialWorkerImage::fromId($serialWorker);
            Log::debug("Signalling $signalName " . $image->getId()
                    . " " . $image->getPid());
            posix_kill($image->getPid(), $signal);
        }

    }

    private function signalWorkers($signal, $signalName) {
        $workers = WorkerImage::all();
        foreach ($workers as $worker) {
            $image = WorkerImage::fromId($worker);
            Log::debug("Signalling $signalName " . $image->getId()
                    . " " . $image->getPid());
            posix_kill($image->getPid(), $signal);
        }

    }

    /**
     * Starts new serial worker on specified serial queue and assigns specified worker as its
     * parent.
     *
     * @param WorkerImage $parent
     * @param string $queueToStart
     */
    private function startSerialQueue(WorkerImage $parent, $queueToStart) {
        Log::debug("Starting serial worker on queue $queueToStart");
        $lock = new QueueLock($queueToStart);
        if (!$lock->acquire()) {
            Log::notice("Failed to acquire lock for serial queue $queueToStart.");

            return;
        }

        Resque\Job\SerialJobLink::unregister($queueToStart);

        try {
            if (Process::fork() === 0) {
                $this->unregisterSigHandlers(); // do not keep handlers from main process in child
                $serialWorker = new SerialWorker(SerialWorkerImage::create($queueToStart), $lock);
                $serialWorker->work($parent->getId());
                $lock->release();
                exit(0);
            }
        } catch (Exception $e) {
            Log::error("Failed to start serial worker on $queueToStart.", ['exception' => $e]);
        }
    }

    private function unregisterSigHandlers() {
        SignalHandler::instance()->unregisterAll();
    }
}