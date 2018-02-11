<?php

namespace Resque\Maintenance;

use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Job\IJobSource;
use Resque\Key;
use Resque\Log;
use Resque\Pool\StaticPool;
use Resque\Process;
use Resque\RedisError;
use Resque\Resque;
use Resque\SignalHandler;
use Resque\Worker\WorkerImage;
use Resque\Worker\WorkerProcess;

class StaticPoolMaintainer implements IProcessMaintainer {

    /** @var StaticPool */
    private $pool;
    /** @var string */
    private $processSetKey;

    /**
     * @param string $poolName
     *
     * @throws ConfigException
     */
    public function __construct($poolName) {
        $this->processSetKey = Key::localPoolProcesses($poolName);
        $this->pool = GlobalConfig::getInstance()->getStaticPoolConfig()->getPool($poolName);
    }


    /**
     * @return WorkerImage[]
     * @throws \Resque\RedisError
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
     * @throws \Resque\RedisError
     */
    public function maintain() {
        $workerLimit = $this->pool->getWorkerCount();

        $alive = $this->cleanupWorkers($workerLimit);

        for ($i = $alive; $i < $workerLimit; $i++) {
            $this->forkWorker();
        }
    }

    /**
     * @param int $workerLimit
     *
     * @return int
     * @throws RedisError
     */
    private function cleanupWorkers($workerLimit) {
        $alive = 0;
        foreach ($this->getLocalProcesses() as $image) {
            // cleanup if dead
            if (!$image->isAlive()) {
                Log::notice("Cleaning up dead {$this->pool->getName()} worker.", [
                    'process_id' => $image->getId()
                ]);
                $image->unregister();
                $this->clearBuffer($this->pool->createJobSource($image));
                continue;
            }

            // kill and cleanup
            if ($alive >= $workerLimit) {
                Log::notice("Terminating {$this->pool->getName()} worker process.", [
                    'worker_id' => $image->getId()
                ]);
                posix_kill($image->getPid(), SIGTERM);
                continue;
            }

            $alive++;
        }

        return $alive;
    }

    /**
     * @param IJobSource $jobSource
     *
     * @throws RedisError
     */
    private function clearBuffer(IJobSource $jobSource) {
        while (($buffered = $jobSource->bufferPop()) !== null) {
            Log::error("Found non-empty buffer for {$this->pool->getName()} worker.", [
                'payload' => $buffered->toString()
            ]);
        }
    }

    /**
     * @throws RedisError
     */
    private function forkWorker() {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->pool->getName(), 's');
            $jobSource = $this->pool->createJobSource($image);
            $this->clearBuffer($jobSource);

            $worker = new WorkerProcess($jobSource, $image);
            if ($worker === null) {
                exit(0);
            }

            try {
                $worker->register();
                $worker->work();
                $worker->unregister();
            } catch (\Throwable $t) {
                Log::error('Worker process failed.', [
                    'exception' => $t,
                    'process_id' => $image->getId()
                ]);
            }
            exit(0);
        }
    }

}