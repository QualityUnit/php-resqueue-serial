<?php

namespace Resque\Protocol;

use Resque\Key;
use Resque\Log;
use Resque\Resque;

class UniqueList {

    const KEY_DEFERRED = 'deferred';
    const KEY_STATE = 'state';
    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE, JOB_PAYLOAD ]
     */
    const SCRIPT_ADD_DEFERRED = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if state ~= ARGV[1] then
    return false
end

return redis.call('SETNX', KEYS[2], ARGV[2])
LUA;
    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE ]
     */
    const SCRIPT_FINALIZE = /** @lang Lua */
        <<<LUA
if redis.call('GET', KEYS[1]) ~= ARGV[1] then
    return false
end
local deferred = redis.call('GET', KEYS[2])
redis.call('DEL', KEYS[1], KEYS[2])

return deferred
LUA;

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    /**
     * @param \Resque\Protocol\Job $job job to create unique record for
     *
     * @throws DeferredException if job was deferred and should not be queued
     * @throws UniqueException if adding wasn't successful and job could not be deferred
     * @throws \Resque\RedisError
     */
    public static function add(Job $job) {
        if (self::addIfNotExists($job)) {
            return;
        }

        if ($job->getUid()->isDeferred() && !self::addDeferred($job)) {
            throw new DeferredException('Job was deferred.');
        }

        throw new UniqueException($job->getUniqueId());
    }

    /**
     * @param Job $job
     *
     * @return bool false if unique state already exists, true otherwise
     * @throws \Resque\RedisError
     */
    public static function addIfNotExists(Job $job) {
        $uniqueId = $job->getUniqueId();

        return !$uniqueId || Resque::redis()->setNx(Key::uniqueState($uniqueId), self::STATE_QUEUED);
    }

    /**
     * @param string $uniqueId
     * @param string $newState
     *
     * @return bool
     * @throws \Resque\RedisError
     */
    public static function editState($uniqueId, $newState) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
            || Resque::redis()->set(Key::uniqueState($uniqueId), $newState, ['XX']);
    }

    /**
     * If deferred job exists on specified unique key, set unique state to QUEUED and return the
     * deferred job. Otherwise clear whole unique info for specified id.
     *
     * @param string $uniqueId
     *
     * @return false|string Return false
     * @throws \Resque\RedisError
     */
    public static function finalize($uniqueId) {
        if (!$uniqueId) {
            return false;
        }

        return Resque::redis()->eval(
                self::SCRIPT_FINALIZE,
                [
                    Key::uniqueState($uniqueId),
                    Key::uniqueDeferred($uniqueId)
                ],
                [self::STATE_RUNNING]
            );
    }

    /**
     * @param string $uniqueId
     *
     * @return bool
     * @throws \Resque\RedisError
     */
    public static function removeAll($uniqueId) {
        return !$uniqueId
            || Resque::redis()->del([
                Key::uniqueState($uniqueId),
                Key::uniqueDeferred($uniqueId)
            ]);
    }

    /**
     * @param string $uniqueId
     *
     * @return bool
     * @throws \Resque\RedisError
     */
    public static function removeDeferred($uniqueId) {
        return !$uniqueId
            || Resque::redis()->del(Key::uniqueDeferred($uniqueId));
    }

    /**
     * @param Job $job
     *
     * @return bool
     * @throws \Resque\RedisError
     */
    private static function addDeferred(Job $job) {
        $uid = $job->getUid();
        if ($uid === null || !$uid->isDeferred()) {
            Log::error('Attempted to defer non-deferrable job.', [
                'payload' => $job->toArray()
            ]);
            throw new \RuntimeException('Only deferrable jobs can be deferred.');
        }

        return false !== Resque::redis()->eval(
                self::SCRIPT_ADD_DEFERRED,
                [
                    Key::uniqueState($uid->getId()),
                    Key::uniqueDeferred($uid->getId())
                ],
                [
                    self::STATE_RUNNING,
                    $job->toString()
                ]
            );
    }
}