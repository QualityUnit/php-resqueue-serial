<?php

namespace Resque\Pool;

use Resque\Key;

class BatchPool {

    /**
     * KEYS [SOURCE_WORKERS_KEY, BACKLOG_LIST_KEY, UNIT_SET_KEY, POOL_QUEUES_KEY]
     * ARGS [SOURCE_ID, BATCH_ID, BATCH_KEY, UNIT_QUEUE_KEY]
     */
    const SCRIPT_ASSIGN_BATCH = /* @lang Lua */
        <<<LUA
if redis.call('hexists', KEYS[1], ARGV[1]) then
    return redis.call('rpush', KEYS[2], ARGV[2])
end 
local added = redis.call('sadd', KEYS[3], ARGV[3])
redis.call('zincrby', KEYS[4], added, ARGV[4])
LUA;

    /**
     * KEYS [UNIT_QUEUE_KEY, POOL_QUEUES_KEY, SOURCE_WORKERS_KEY, BACKLOG_KEY, COMMITTED_KEY]
     * ARGS [BATCH_KEY, SOURCE_ID]
     */
    const SCRIPT_REMOVE_BATCH = /* @lang Lua */
        <<<LUA
local val = -redis.call('srem', KEYS[1], ARGV[1])
redis.call('zincrby', KEYS[2], val, KEYS[1])
redis.call('hdel', KEYS[3], ARGV[2])
redis.call('rpoplpush', KEYS[4], KEYS[5])
LUA;

    public function assignBatch(BatchImage $batch) {
        try {
            $this->execAssignBatchScript($batch);
        } catch (\Exception $e) {

        }
    }

    public function removeBatch(BatchImage $batch) {
        try {
            $this->execRemoveBatchScript($batch);
        } catch (\Exception $e) {

        }
    }

    private function execAssignBatchScript(BatchImage $batch) {
        return \Resque::redis()->eval(
            self::SCRIPT_ASSIGN_BATCH,
            [
                Key::batchPoolSourceWorker($poolName),
                Key::batchPoolBacklogList($poolName, $batch->getSourceId()),
                Key::batchPoolUnitQueueSet($poolName, $unitId),
                Key::batchPoolQueuesSortedSet($poolName)
            ],
            [
                $batch->getSourceId(),
                $batch->getId(),
                $batch->getBatchKey(),
                Key::batchPoolUnitQueueSet($poolName, $unitId),
            ]
        );
    }

    private function execRemoveBatchScript(BatchImage $batch) {
        return \Resque::redis()->eval(
            self::SCRIPT_REMOVE_BATCH,
            [
                Key::batchPoolUnitQueueSet($poolName, $unitId),
                Key::batchPoolQueuesSortedSet($poolName),
                Key::batchPoolSourceWorker($poolName),
                Key::batchPoolBacklogList($poolName, $batch->getSourceId()),
                Key::committedBatchList()
            ],
            [
                $batch->getBatchKey(),
                $batch->getSourceId()
            ]
        );
    }
}