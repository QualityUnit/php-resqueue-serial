<?php

namespace Resque\Maintenance;

use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Job\IJobSource;
use Resque\Key;
use Resque\Log;
use Resque\Pool\BatchPool;
use Resque\Process;
use Resque\Process\IProcessImage;
use Resque\Resque;
use Resque\SignalHandler;
use Resque\Stats\PoolStats;
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
    /** @var int */
    private $unitCount;
    /** @var int */
    private $workersPerUnit;

    /**
     * @param string $poolName
     *
     * @throws ConfigException
     */
    public function __construct($poolName) {
        $this->processSetKey = Key::localPoolProcesses($poolName);
        $this->pool = GlobalConfig::getInstance()->getBatchPoolConfig()->getPool($poolName);
        $this->workersPerUnit = $this->pool->getWorkersPerUnit();
        $this->unitCount = $this->pool->getUnitCount();
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
        $unitsAlive = $this->cleanupUnits();
        foreach ($unitsAlive as $unitNumber => $workerCount) {
            $this->createUnitWorkers($unitNumber, $this->workersPerUnit - $workerCount);
        }

        $this->cleanupUnitQueues();
    }

    /**
     * @throws \Resque\RedisError
     */
    private function cleanupUnitQueues() {
        $poolQueuesKey = Key::batchPoolQueuesSortedSet($this->pool->getName());
        foreach ($this->getUnitQueueKeys() as $unitQueueKey) {
            Resque::redis()->zIncrBy($poolQueuesKey, 0, $unitQueueKey);
        }

        $localNodeId = GlobalConfig::getInstance()->getNodeId();

        $batchesInQueue = 0;

        $keys = Resque::redis()->zRange($poolQueuesKey, 0, -1);
        foreach ($keys as $key) {
            $unitId = explode(':', $key)[2];
            list($nodeId, $unitNumber) = $this->pool->parseUnitId($unitId);
            if ($nodeId !== $localNodeId) {
                continue;
            }

            $batchesInQueue += Resque::redis()->lLen($key);

            if ($unitNumber >= $this->unitCount) {
                Log::notice("Clearing unit $unitId queue");
                $this->clearQueue($unitId);
            }
        }

        PoolStats::instance()->reportQueue($this->pool->getName(), $batchesInQueue);
    }

    /**
     * @return int[]
     * @throws \Resque\RedisError
     */
    private function cleanupUnits() {
        $counts = array_fill(0, $this->unitCount, 0);

        foreach ($this->getLocalProcesses() as $image) {
            if (!$image->isAlive()) {
                Log::notice("Cleaning up dead {$this->pool->getName()} worker.", [
                    'process_id' => $image->getId()
                ]);
                $image->unregister();
                $this->clearBuffer($this->pool->createJobSource($image));
                continue;
            }

            $unitNumber = $image->getCode();
            if ($unitNumber >= $this->unitCount || $counts[$unitNumber] >= $this->workersPerUnit) {
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
     * @throws \Resque\RedisError
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
     * @throws \Resque\RedisError
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
     * @throws \Resque\RedisError
     */
    private function createUnitWorkers($unitNumber, $workersToCreate) {
        for ($i = 0; $i < $workersToCreate; $i++) {
            $this->forkWorker($unitNumber);
        }
    }

    /**
     * @param $unitNumber
     *
     * @throws \Resque\RedisError
     */
    private function forkWorker($unitNumber) {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->pool->getName(), $unitNumber);
            Log::info("Creating batch pool worker {$image->getId()}");
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
     * @return string[]
     */
    private function getUnitQueueKeys() {
        $keys = [];
        for ($i = 0; $i < $this->unitCount; $i++) {
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