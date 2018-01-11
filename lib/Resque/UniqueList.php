<?php

namespace Resque;

use Resque;
use Resque\Api\DeferredException;
use Resque\Api\Job;
use Resque\Api\UniqueException;

class UniqueList {

    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE ]
     */
    const SCRIPT_FINALIZE = <<<LUA
if redis.call('GET', KEYS[1]) ~= ARGV[1] then
    return false
end
local deferred = redis.call('GET', KEYS[2])
redis.call('DEL', KEYS[1], KEYS[2])

return deferred
LUA;

    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE, JOB_PAYLOAD ]
     */
    const SCRIPT_ADD_DEFERRED = <<<LUA
local state = redis.call('GET', KEYS[1])
if state ~= ARGV[1] then
    return false
end

return redis.call('SETNX', KEYS[2], ARGV[2])
LUA;

    const KEY_STATE = 'state';
    const KEY_DEFERRED = 'deferred';

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    /**
     * @param Job $job job to create unique record for
     * @param bool $ignoreFail if true, ignore already existing unique record
     *
     * @throws DeferredException if job was deferred and should not be queued
     * @throws UniqueException if adding wasn't successful and job could not be deferred
     */
    public static function add(Job $job, $ignoreFail = false) {
        $uniqueId = $job->getUniqueId();

        if (!$uniqueId
                || Resque::redis()->setNx(Key::uniqueState($uniqueId), self::STATE_QUEUED)
                || $ignoreFail) {
            return;
        }

        if ($job->getUid()->isDeferred() && self::addDeferred($job) !== false) {
            throw new DeferredException('Job was deferred.');
        }

        throw new UniqueException($job->getUniqueId());
    }

    public static function addDeferred(Job $job) {
        $uid = $job->getUid();
        if ($uid == null || !$uid->isDeferred()) {
            throw new \RuntimeException('Only deferrable jobs can be deferred.');
        }

        return Resque::redis()->eval(
                self::SCRIPT_ADD_DEFERRED,
                [
                        Redis::getPrefix() . Key::uniqueState($uid->getId()),
                        Redis::getPrefix() . Key::uniqueDeferred($uid->getId())
                ],
                [self::STATE_RUNNING, $job->toString()]
        );
    }

    public static function editState($uniqueId, $newState) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
                || Resque::redis()->set(Key::uniqueState($uniqueId), $newState, ['XX']);
    }

    /**
     * If deferred job exists on specified unique key, set unique state to QUEUED and return the
     * deferred job. Otherwise clear whole unique info for specified id.
     *
     * @param $uniqueId
     *
     * @return false|string Return false
     */
    public static function finalize($uniqueId) {
        return !$uniqueId
                || Resque::redis()->eval(
                        self::SCRIPT_FINALIZE,
                        [
                                Redis::getPrefix() . Key::uniqueState($uniqueId),
                                Redis::getPrefix() . Key::uniqueDeferred($uniqueId)
                        ],
                        [self::STATE_RUNNING]
                );
    }

    public static function removeAll($uniqueId) {
        return !$uniqueId
                || Resque::redis()->del([
                        Key::uniqueState($uniqueId),
                        Key::uniqueDeferred($uniqueId)
                ]);
    }

    public static function removeDeferred($uniqueId) {
        return !$uniqueId
                || Resque::redis()->del(Key::uniqueDeferred($uniqueId));
    }
}