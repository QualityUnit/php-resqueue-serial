<?php

namespace Resque\Pool;

use Resque;
use Resque\Key;

class BatchPool {

    /**
     * KEYS [SOURCE_WORKERS_KEY, BACKLOG_LIST_KEY, UNIT_QUEUES_SET_KEY, POOL_QUEUES_KEY]
     * ARGS [SOURCE_ID, BATCH_ID, BATCH_KEY]
     */
    const SCRIPT_ASSIGN_BATCH = /* @lang Lua */
        <<<LUA
if redis.call('hexists', KEYS[1], ARGV[1]) then
    return redis.call('rpush', KEYS[2], ARGV[2])
end 
local added = redis.call('sadd', KEYS[3], ARGV[3])
redis.call('zincrby', KEYS[4], added, KEYS[3])
LUA;

    /**
     * KEYS [UNIT_QUEUES_SET_KEY, POOL_QUEUES_KEY, SOURCE_WORKERS_KEY, BACKLOG_LIST_KEY, COMMITTED_BATCH_LIST_KEY]
     * ARGS [BATCH_KEY, SOURCE_ID]
     */
    const SCRIPT_REMOVE_BATCH = /* @lang Lua */
        <<<LUA
local val = -redis.call('srem', KEYS[1], ARGV[1])
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
     *
     * @throws PoolStateException
     */
    public function assignBatch(BatchImage $batch) {
        $unitId = $this->getNextUnitId();

        Resque::redis()->eval(
            self::SCRIPT_ASSIGN_BATCH,
            [
                Key::batchPoolSourceWorker($this->poolName),
                Key::batchPoolBacklogList($this->poolName, $batch->getSourceId()),
                Key::batchPoolUnitQueueSet($this->poolName, $unitId),
                Key::batchPoolQueuesSortedSet($this->poolName)
            ],
            [
                $batch->getSourceId(),
                $batch->getId(),
                $batch->getBatchKey(),
            ]
        );
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
     */
    public function removeBatch(BatchImage $batch, $unitId) {
        Resque::redis()->eval(
            self::SCRIPT_REMOVE_BATCH,
            [
                Key::batchPoolUnitQueueSet($this->poolName, $unitId),
                Key::batchPoolQueuesSortedSet($this->poolName),
                Key::batchPoolSourceWorker($this->poolName),
                Key::batchPoolBacklogList($this->poolName, $batch->getSourceId()),
                Key::committedBatchList()
            ],
            [
                $batch->getBatchKey(),
                $batch->getSourceId()
            ]
        );
    }

    /**
     * @return string
     * @throws PoolStateException
     */
    private function getNextUnitId() {
        $result = Resque::redis()->zRange(Key::batchPoolQueuesSortedSet($this->poolName), 0, 0);

        if (!\is_array($result) || \count($result) !== 1) {
            throw new PoolStateException("No units available for pool {$this->poolName}.");
        }

        return $result[0];
    }
}