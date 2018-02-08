<?php

namespace Resque\Maintenance;

use Resque;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
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
for _,batch_key in ipairs(redis.call('smembers', KEYS[1], 0, -1)) do
    local new_set = redis.call('zrange', KEYS[2], 0, 0);
    local added = redis.call('sadd', new_set, batch_key)
    redis.call('zincrby', KEYS[2], added, new_set)
end
LUA;

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
        $pool = GlobalConfig::getInstance()->getBatchPoolConfig()->getPool($this->poolName);

        $unitCount = $pool->getUnitCount();
        $workersPerUnit = $pool->getWorkersPerUnit();

        $unitsAlive = $this->cleanupUnits($unitCount, $workersPerUnit);
        foreach ($unitsAlive as $unitNumber => $workerCount) {
            $this->createUnitWorkers($unitNumber, $workersPerUnit - $workerCount);
        }

        $this->cleanupUnitQueues($unitCount);
    }

    /**
     * @param int $unitCount
     * @throws Resque\Api\RedisError
     */
    private function cleanupUnitQueues($unitCount) {
        $poolQueuesKey = Key::batchPoolQueuesSortedSet($this->poolName);
        $keys = Resque::redis()->zRange($poolQueuesKey, 0, -1);

        foreach ($this->getUnitQueueKeys($unitCount) as $unitQueueKey) {
            Resque::redis()->zIncrBy($poolQueuesKey, 0, $unitQueueKey);
        }

        $localNodeId = GlobalConfig::getInstance()->getNodeId();

        foreach ($keys as $key) {
            $unitId = explode(':', $key)[2];
            list($nodeId, $unitNumber) = $this->parseUnitId($unitId);
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

        foreach ($this->getLocalProcesses() as $process) {
            if (!$process->isAlive()) {
                Resque::redis()->sRem($this->processSetKey, $process->getId());
                continue;
            }

            list(, $unitNumber) = $this->parseUnitId($process->getCode());
            if ($unitNumber >= $unitCount || $counts[$unitNumber] >= $workersPerUnit) {
                $this->terminateWorker($process);
                continue;
            }

            $counts[$unitNumber]++;
        }

        return $counts;
    }

    /**
     * @param string $unitId
     * @throws Resque\Api\RedisError
     */
    private function clearQueue($unitId) {
        Resque::redis()->eval(
            self::SCRIPT_CLEAR_UNIT_QUEUE,
            [
                Key::batchPoolUnitQueueSet($this->poolName, $unitId),
                Key::batchPoolQueuesSortedSet($this->poolName)
            ]
        );
    }

    /**
     * @param string $unitNumber
     * @param int $workersToCreate
     */
    private function createUnitWorkers($unitNumber, $workersToCreate) {
        for ($i = 0; $i < $workersToCreate; $i++) {
            $this->forkWorker($unitNumber);
        }
    }

    private function forkWorker($unitNumber) {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->poolName, $this->resolveLocalUnitId($unitNumber));
            $worker = new WorkerProcess($batchSource, $image);
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

    /**
     * @param int $unitCount
     *
     * @return string[]
     */
    private function getUnitQueueKeys($unitCount) {
        $keys = [];
        for ($i = 0; $i < $unitCount; $i++) {
            $keys[] = Key::batchPoolUnitQueueSet($this->poolName, $this->resolveLocalUnitId($i));
        }

        return $keys;
    }

    /**
     * @param string $unitId
     *
     * @return mixed[] values [nodeId, unitNumber]
     */
    private function parseUnitId($unitId) {
        return explode('-', $unitId);
    }

    /**
     * @param $unitNumber
     *
     * @return string
     */
    private function resolveLocalUnitId($unitNumber) {
        $nodeId = GlobalConfig::getInstance()->getNodeId();

        return "$nodeId-$unitNumber";
    }

    /**
     * @param IProcessImage $image
     */
    private function terminateWorker(IProcessImage $image) {
        Log::notice("Terminating {$this->poolName} worker process");
        posix_kill($image->getPid(), SIGTERM);
    }
}