<?php

namespace Resque\Maintenance;

use Resque;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\SignalHandler;
use Resque\Worker\WorkerImage;

class StaticPoolMaintainer implements IProcessMaintainer {

    /** @var string */
    private $poolName;
    /** @var string */
    private $processSetKey;

    /**
     * @param string $poolName
     */
    public function __construct($poolName) {
        $this->poolName = $poolName;
        $this->processSetKey = Key::localPoolProcesses($this->poolName);
    }


    /**
     * @return WorkerImage[]
     * @throws Resque\Api\RedisError
     */
    public function getLocalProcesses() {
        $workerIds = Resque::redis()->sMembers($this->processSetKey);

        $images = [];
        foreach ($workerIds as $workerId) {
            $images[] = WorkerImage::load($workerId);
        }

        return $images;
    }

    /**
     * Cleans up and recovers local processes.
     *
     * @throws ConfigException
     * @throws Resque\Api\RedisError
     */
    public function maintain() {
        $workerLimit = GlobalConfig::getInstance()->getStaticPoolConfig()->getPool($this->poolName)->getWorkerCount();

        $alive = $this->cleanupWorkers($workerLimit);

        for ($i = $alive; $i < $workerLimit; $i++) {
            $this->forkWorker();
        }
    }

    /**
     * @param int $workerLimit
     *
     * @return int
     * @throws Resque\Api\RedisError
     */
    private function cleanupWorkers($workerLimit) {
        $alive = 0;
        foreach ($this->getLocalProcesses() as $image) {
            // cleanup if dead
            if (!$image->isAlive()) {
                Resque::redis()->sRem($this->processSetKey, $image->getId());
                continue;
            }

            // kill and cleanup
            if ($alive >= $workerLimit) {
                Log::notice("Terminating {$this->poolName} worker process");
                posix_kill($image->getPid(), SIGTERM);
                continue;
            }

            $alive++;
        }

        return $alive;
    }

    private function forkWorker() {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->poolName, 'static');
            $worker = new StaticWorkerProcess($image);
            if ($worker === null) {
                exit(0);
            }

            try {
                $worker->register();
                $worker->work();
            } catch (\Throwable $t) {
                Log::error('Worker process failed.', [
                    'exception' => $t,
                    'process_id' => $image->getId()
                ]);
            } finally {
                $worker->unregister();
            }
            exit(0);
        }
    }

}