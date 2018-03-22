<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Job\BatchJobSource;
use Resque\Key;
use Resque\Log;
use Resque\Queue\JobQueue;
use Resque\Resque;
use Resque\Worker\WorkerImage;

class BatchPool implements IPool {

    /**
     * KEYS [SOURCE_NODES_KEY, BACKLOG_LIST_KEY, UNIT_QUEUES_LIST_KEY, POOL_QUEUES_KEY]
     * ARGS [SOURCE_ID, BATCH_ID, UNIT_QUEUES_LIST_KEY, NODE_ID]
     */
    const SCRIPT_ASSIGN_BATCH = /* @lang Lua */
        <<<LUA
if redis.call('hexists', KEYS[1], ARGV[1]) == 1 then
    return redis.call('lpush', KEYS[2], ARGV[2])
end
redis.call('hset', KEYS[1], ARGV[4])
local size = redis.call('lpush', KEYS[3], ARGV[2])
redis.call('zadd', KEYS[4], size, ARGV[3])
LUA;

    /**
     * KEYS [UNIT_QUEUES_LIST_KEY, POOL_QUEUES_KEY, SOURCE_NODES_KEY, BACKLOG_LIST_KEY, COMMITTED_BATCH_LIST_KEY]
     * ARGS [BATCH_ID, SOURCE_ID, UNIT_QUEUES_LIST_KEY]
     */
    const SCRIPT_REMOVE_BATCH = /* @lang Lua */
        <<<LUA
local val = -redis.call('lrem', KEYS[1], 0, ARGV[1])
if (val == 0) then return end
redis.call('zincrby', KEYS[2], val, ARGV[3])
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
     * @throws \Resque\RedisError
     */
    public static function assignBatch(BatchImage $batch, $poolName) {
        Log::debug("Assigning batch {$batch->getId()} to pool $poolName");
        $unitQueueKey = self::getNextUnitQueueKey($poolName);

        Resque::redis()->eval(
            self::SCRIPT_ASSIGN_BATCH,
            [
                Key::batchPoolSourceNodes($poolName),
                Key::batchPoolBacklogList($poolName, $batch->getSourceId()),
                $unitQueueKey,
                Key::batchPoolQueuesSortedSet($poolName)
            ],
            [
                $batch->getSourceId(),
                $batch->getId(),
                $unitQueueKey,
                GlobalConfig::getInstance()->getNodeId()
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

        $unitId = $this->createLocalUnitId($workerImage->getCode());
        $queueListKey = Key::batchPoolUnitQueueList($this->poolName, $unitId);

        return new BatchJobSource(
            $queueListKey,
            $bufferQueue,
            function (BatchImage $image) use ($unitId) {
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
     * @throws \Resque\RedisError
     */
    public function removeBatch(BatchImage $batch, $unitId) {
        $unitQueueListKey = Key::batchPoolUnitQueueList($this->poolName, $unitId);
        Resque::redis()->eval(
            self::SCRIPT_REMOVE_BATCH,
            [
                $unitQueueListKey,
                Key::batchPoolQueuesSortedSet($this->poolName),
                Key::batchPoolSourceNodes($this->poolName),
                Key::batchPoolBacklogList($this->poolName, $batch->getSourceId()),
                Key::committedBatchList()
            ],
            [
                $batch->getId(),
                $batch->getSourceId(),
                $unitQueueListKey
            ]
        );
    }

    /**
     * @param string $poolName
     *
     * @return string
     * @throws PoolStateException
     * @throws \Resque\RedisError
     */
    private static function getNextUnitQueueKey($poolName) {
        $result = Resque::redis()->zRange(Key::batchPoolQueuesSortedSet($poolName), 0, 0);

        if (!\is_array($result) || \count($result) !== 1) {
            throw new PoolStateException("No units available for pool $poolName.");
        }

        return $result[0];
    }
}