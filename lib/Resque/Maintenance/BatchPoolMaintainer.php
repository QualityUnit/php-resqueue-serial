<?php

namespace Resque\Maintenance;

use Resque;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Job\IJobSource;
use Resque\Key;
use Resque\Log;
use Resque\Pool\BatchPool;
use Resque\Process;
use Resque\Process\IProcessImage;
use Resque\SignalHandler;
use Resque\Worker\WorkerImage;
use Resque\Worker\WorkerProcess;

class BatchPoolMaintainer implements IProcessMaintainer {
    /**
     * KEYS [UNIT_QUEUES_SET_KEY, POOL_QUEUES_KEY]
     * ARGS []
     */
    const SCRIPT_CLEAR_UNIT_QUEUE = /* @lang Lua */
        <<<LUA
redis.call('zrem', KEYS[2], KEYS[1]);
local b_key = redis.call('rpop', KEYS[1])
while b_key ~= false do
 local to_l = redis.call('zrange', KEYS[2], 0, 0);
 local size = redis.call('lpush', to_l, b_key)
 redis.call('zadd', KEYS[2], size, to_l)
 b_key = redis.call('rpop', KEYS[1])
end
LUA;

    /** @var BatchPool */
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
        $this->pool = GlobalConfig::getInstance()->getBatchPoolConfig()->getPool($poolName);
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
     * @throws Resque\Api\RedisError
     */
    public function maintain() {
        $unitCount = $this->pool->getUnitCount();
        $workersPerUnit = $this->pool->getWorkersPerUnit();

        $unitsAlive = $this->cleanupUnits($unitCount, $workersPerUnit);
        foreach ($unitsAlive as $unitNumber => $workerCount) {
            $this->createUnitWorkers($unitNumber, $workersPerUnit - $workerCount);
        }

        $this->cleanupUnitQueues($unitCount);
    }

    /**
     * @param int $unitCount
     *
     * @throws Resque\Api\RedisError
     */
    private function cleanupUnitQueues($unitCount) {
        $poolQueuesKey = Key::batchPoolQueuesSortedSet($this->pool->getName());
        $keys = Resque::redis()->zRange($poolQueuesKey, 0, -1);

        foreach ($this->getUnitQueueKeys($unitCount) as $unitQueueKey) {
            Resque::redis()->zIncrBy($poolQueuesKey, 0, $unitQueueKey);
        }

        $localNodeId = GlobalConfig::getInstance()->getNodeId();

        foreach ($keys as $key) {
            $unitId = explode(':', $key)[2];
            list($nodeId, $unitNumber) = $this->pool->parseUnitId($unitId);
            if ($nodeId !== $localNodeId) {
                continue;
            }

            if ($unitNumber >= $unitCount) {
                $this->clearQueue($unitId);
            }
        }
    }

    /**
     * @param int $unitCount
     * @param int $workersPerUnit
     *
     * @return int[]
     * @throws Resque\Api\RedisError
     */
    private function cleanupUnits($unitCount, $workersPerUnit) {
        $counts = array_fill(0, $unitCount, 0);

        foreach ($this->getLocalProcesses() as $image) {
            if (!$image->isAlive()) {
                Log::notice("Cleaning up dead {$this->pool->getName()} worker.", [
                    'process_id' => $image->getId()
                ]);
                $image->unregister();
                $this->clearBuffer($this->pool->createJobSource($image->getId()));
                continue;
            }

            list(, $unitNumber) = $this->pool->parseUnitId($image->getCode());
            if ($unitNumber >= $unitCount || $counts[$unitNumber] >= $workersPerUnit) {
                $this->terminateWorker($image);
                continue;
            }

            $counts[$unitNumber]++;
        }

        return $counts;
    }

    /**
     * @param IJobSource $jobSource
     *
     * @throws Resque\Api\RedisError
     */
    private function clearBuffer(IJobSource $jobSource) {
        while (($buffered = $jobSource->bufferPop()) !== null) {
            Log::error("Found non-empty buffer for {$this->pool->getName()} worker.", [
                'payload' => $buffered->toString()
            ]);
        }
    }

    /**
     * @param string $unitId
     *
     * @throws Resque\Api\RedisError
     */
    private function clearQueue($unitId) {
        Resque::redis()->eval(
            self::SCRIPT_CLEAR_UNIT_QUEUE,
            [
                Key::batchPoolUnitQueueList($this->pool->getName(), $unitId),
                Key::batchPoolQueuesSortedSet($this->pool->getName())
            ]
        );
    }

    /**
     * @param string $unitNumber
     * @param int $workersToCreate
     *
     * @throws Resque\Api\RedisError
     */
    private function createUnitWorkers($unitNumber, $workersToCreate) {
        for ($i = 0; $i < $workersToCreate; $i++) {
            $this->forkWorker($unitNumber);
        }
    }

    /**
     * @param $unitNumber
     *
     * @throws Resque\Api\RedisError
     */
    private function forkWorker($unitNumber) {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->pool->getName(), $this->pool->createLocalUnitId($unitNumber));
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

    /**
     * @param int $unitCount
     *
     * @return string[]
     */
    private function getUnitQueueKeys($unitCount) {
        $keys = [];
        for ($i = 0; $i < $unitCount; $i++) {
            $keys[] = Key::batchPoolUnitQueueList($this->pool->getName(), $this->pool->createLocalUnitId($i));
        }

        return $keys;
    }

    /**
     * @param IProcessImage $image
     */
    private function terminateWorker(IProcessImage $image) {
        Log::notice("Terminating {$this->pool->getName()} worker process");
        posix_kill($image->getPid(), SIGTERM);
    }
}