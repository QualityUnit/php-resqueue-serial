<?php


namespace Resque\Init;


use Exception;
use Resque;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Process;
use Resque\Scheduler\SchedulerProcess;
use Resque\SignalHandler;
use Resque\Worker\StandardWorker;
use Resque\Worker\WorkerImage;

class InitProcess {

    private $stopping = false;
    private $reloaded = false;

    public function maintain() {
        Process::setTitle('maintaining');
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
        Log::debug('========= Starting maintenance');

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
                    } catch (\Throwable $t) {
                        Log::critical('Worker failed unexpectedly.', [
                            'exception' => $t,
                            'pid' => posix_getpid()
                        ]);
                    }
                    exit(1);
                }
            }

            Log::debug("====== Finished maintenance of queue $queue");

            // check interruption
            SignalHandler::dispatch();
            if ($this->stopping || $this->reloaded) {
                Log::debug('========= Received stop or reload signal, halting maintenance...');

                return;
            }
        }
        Log::debug('========= Maintenance ended');
    }

    public function reload() {
        Log::debug('Reloading configuration');
        GlobalConfig::reload();
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix('init-process');
        $this->reloaded = true;

        $this->signalWorkers(SIGHUP, 'HUP');
        $this->signalScheduler(SIGHUP, 'HUP');
    }

    /**
     * send TERM to all workers and serial workers
     */
    public function shutdown() {
        $this->stopping = true;

        $this->signalWorkers(SIGTERM, 'TERM');
        $this->signalScheduler(SIGTERM, 'TERM');
    }

    public function start() {
        Process::setTitlePrefix('init');
        Process::setTitle('starting');
        $this->initialize();
        $this->recover();
    }

    /**
     * Removes dead workers from queue pool and counts the number of living ones.
     *
     * @param string $queue
     *
     * @return int number of living workers on specified queue
     */
    private function cleanupWorkers($queue) {
        Log::debug('Worker cleanup started');

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
                Log::warning('Cleaning up dead worker.', [
                    'started' => $image->getStarted(),
                    'state' => $image->getState(),
                    'worker_id' => $workerId
                ]);
                // cleanup
                $image
                    ->removeFromPool()
                    ->clearState()
                    ->clearStarted();
            } else {
                $livingWorkers++;
            }
            $totalWorkers++;
        }

        Log::debug("Worker cleanup done, processed $totalWorkers workers");

        return $livingWorkers;
    }

    private function initialize() {
        Resque::setBackend(GlobalConfig::getInstance()->getBackend());

        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix('init-process');

        $this->registerSigHandlers();
    }

    private function maintainScheduler() {
        $pid = SchedulerProcess::getLocalPid();
        if ($pid && posix_getpgid($pid) > 0) {
            return;
        }

        $pid = Process::fork();
        if ($pid === false) {
            throw new Exception('Fork returned false.');
        }

        if ($pid == 0) {
            $worker = new SchedulerProcess();
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
        $pid = SchedulerProcess::getLocalPid();
        Log::debug("Signalling $signalName to scheduler $pid");
        posix_kill($pid, $signal);
    }

    private function signalWorkers($signal, $signalName) {
        $workers = WorkerImage::all();
        foreach ($workers as $worker) {
            $image = WorkerImage::fromId($worker);
            Log::debug("Signalling $signalName to {$image->getId()}");
            posix_kill($image->getPid(), $signal);
        }

    }

    private function unregisterSigHandlers() {
        SignalHandler::instance()->unregisterAll();
    }
}