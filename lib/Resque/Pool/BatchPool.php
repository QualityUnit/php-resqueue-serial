<?php

namespace Resque\Pool;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Config\IPool;
use Resque\Job\BatchJobSource;
use Resque\Key;
use Resque\Queue\JobQueue;
use Resque\Worker\WorkerImage;

class BatchPool implements IPool {

    /**
     * KEYS [SOURCE_WORKERS_KEY, BACKLOG_LIST_KEY, UNIT_QUEUES_SET_KEY, POOL_QUEUES_KEY]
     * ARGS [SOURCE_ID, BATCH_ID]
     */
    const SCRIPT_ASSIGN_BATCH = /* @lang Lua */
        <<<LUA
if redis.call('hexists', KEYS[1], ARGV[1]) then
    return redis.call('lpush', KEYS[2], ARGV[2])
end 
local size = redis.call('lpush', KEYS[3], ARGV[2])
redis.call('zadd', KEYS[4], size, KEYS[3])
LUA;

    /**
     * KEYS [UNIT_QUEUES_SET_KEY, POOL_QUEUES_KEY, SOURCE_WORKERS_KEY, BACKLOG_LIST_KEY, COMMITTED_BATCH_LIST_KEY]
     * ARGS [BATCH_ID, SOURCE_ID]
     */
    const SCRIPT_REMOVE_BATCH = /* @lang Lua */
        <<<LUA
local val = -redis.call('lrem', KEYS[1], ARGV[1])
if (val == 0) then return end
redis.call('zincrby', KEYS[2], val, KEYS[1])
redis.call('hdel', KEYS[3], ARGV[2])
redis.call('rpoplpush', KEYS[4], KEYS[5])
LUA;

    /** @var string */
    private $poolName;
    /** @var int */
    private $unitCount;
    /** @var int */
    private $workersPerUnit;

    /**
     * @param string $poolName
     * @param int $unitCount
     * @param int $workersPerUnit
     */
    public function __construct($poolName, $unitCount, $workersPerUnit) {
        $this->poolName = $poolName;
        $this->unitCount = (int)$unitCount;
        $this->workersPerUnit = (int)$workersPerUnit;
    }

    /**
     * @param BatchImage $batch
     * @param string $poolName
     *
     * @throws PoolStateException
     * @throws Resque\Api\RedisError
     */
    public static function assignBatch(BatchImage $batch, $poolName) {
        $unitId = self::getNextUnitId($poolName);

        Resque::redis()->eval(
            self::SCRIPT_ASSIGN_BATCH,
            [
                Key::batchPoolSourceWorker($poolName),
                Key::batchPoolBacklogList($poolName, $batch->getSourceId()),
                Key::batchPoolUnitQueueList($poolName, $unitId),
                Key::batchPoolQueuesSortedSet($poolName)
            ],
            [
                $batch->getSourceId(),
                $batch->getId(),
            ]
        );
    }

    /**
     * @param string $unitId
     *
     * @return mixed[] values [nodeId, unitNumber]
     */
    public function parseUnitId($unitId) {
        return explode('-', $unitId);
    }

    /**
     * @param $unitNumber
     *
     * @return string
     */
    public function createLocalUnitId($unitNumber) {
        $nodeId = GlobalConfig::getInstance()->getNodeId();

        return "$nodeId-$unitNumber";
    }

    /**
     * @param WorkerImage $workerImage
     *
     * @return BatchJobSource
     */
    public function createJobSource(WorkerImage $workerImage) {
        $bufferQueue = new JobQueue(Key::workerBuffer($workerImage->getId()));

        $unitId = $workerImage->getCode();
        $queueListKey = Key::batchPoolUnitQueueList($this->poolName, $unitId);

        return new BatchJobSource(
            $queueListKey,
            $bufferQueue,
            function (BatchImage $image) use ($unitId) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $this->removeBatch($image, $unitId);
            }
        );
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->poolName;
    }

    /**
     * @return int
     */
    public function getUnitCount() {
        return $this->unitCount;
    }

    /**
     * @return int
     */
    public function getWorkersPerUnit() {
        return $this->workersPerUnit;
    }

    /**
     * @param BatchImage $batch
     * @param string $unitId
     *
     * @throws Resque\Api\RedisError
     */
    public function removeBatch(BatchImage $batch, $unitId) {
        Resque::redis()->eval(
            self::SCRIPT_REMOVE_BATCH,
            [
                Key::batchPoolUnitQueueList($this->poolName, $unitId),
                Key::batchPoolQueuesSortedSet($this->poolName),
                Key::batchPoolSourceWorker($this->poolName),
                Key::batchPoolBacklogList($this->poolName, $batch->getSourceId()),
                Key::committedBatchList()
            ],
            [
                $batch->getId(),
                $batch->getSourceId()
            ]
        );
    }

    /**
     * @param string $poolName
     *
     * @return string
     * @throws PoolStateException
     * @throws Resque\Api\RedisError
     */
    private static function getNextUnitId($poolName) {
        $result = Resque::redis()->zRange(Key::batchPoolQueuesSortedSet($poolName), 0, 0);

        if (!\is_array($result) || \count($result) !== 1) {
            throw new PoolStateException("No units available for pool $poolName.");
        }

        return $result[0];
    }
}