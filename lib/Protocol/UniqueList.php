<?php

namespace Resque\Protocol;

use Resque\Key;
use Resque\Log;
use Resque\RedisError;
use Resque\Resque;
use Resque\Worker\WorkerImage;

class UniqueList {

    const KEY_DEFERRED = 'deferred';
    const KEY_STATE = 'state';

    /**
     * KEYS [ STATE KEY, DEFERRED KEY, SOURCE KEY ]
     * ARGS [ RUNNING STATE PREFIX ]
     */
    const SCRIPT_ADD_DEFERRED = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if not state or 1 ~= string.find(state, ARGV[1]) then
    return false
end
local payload = redis.call('RPOP', KEYS[3])
redis.call('SETNX', KEYS[2], payload)
return payload
LUA;

    /**
     * KEYS [ STATE KEY, SOURCE KEY, DESTINATION KEY ]
     * ARGS [ QUEUED STATE ]
     */
    const SCRIPT_ADD_UNIQUE = /** @lang Lua */
        <<<LUA
if 1 ~= redis.call('SETNX', KEYS[1], ARGV[1]) then
    return false
end
return redis.call('RPOPLPUSH', KEYS[2], KEYS[3])
LUA;
    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE PREFIX ]
     */
    const SCRIPT_FINALIZE = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if not state or 1 ~= string.find(state, ARGV[1]) then
    return 1
end
local deferred = redis.call('GET', KEYS[2])
redis.call('DEL', KEYS[1], KEYS[2])
return deferred or 1
LUA;

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    /**
     * @param string $uniqueId
     * @param string $sourceKey
     * @param string $destinationKey
     *
     * @return false|string
     * @throws RedisError
     */
    public static function add($uniqueId, $sourceKey, $destinationKey) {
        self::clearIfOld($uniqueId);

        return Resque::redis()->eval(
            self::SCRIPT_ADD_UNIQUE,
            [
                Key::uniqueState($uniqueId),
                $sourceKey,
                $destinationKey
            ],
            [
                self::makeState(self::STATE_QUEUED)
            ]
        );
    }

    /**
     * @param string $uniqueId
     * @param string $sourceKey
     *
     * @return string
     * @throws RedisError
     */
    public static function addDeferred($uniqueId, $sourceKey) {
        return Resque::redis()->eval(
            self::SCRIPT_ADD_DEFERRED,
            [
                Key::uniqueState($uniqueId),
                Key::uniqueDeferred($uniqueId),
                $sourceKey
            ],
            [
                self::STATE_RUNNING
            ]
        );
    }

    /**
     * If deferred job exists on specified unique key, set unique state to QUEUED and return the
     * deferred job. Otherwise clear whole unique info for specified id.
     *
     * @param string $uniqueId
     *
     * @return int|string Return 1 if there's nothing to defer
     * @throws RedisError
     */
    public static function finalize($uniqueId) {
        if (!$uniqueId) {
            return 1;
        }

        return Resque::redis()->eval(
            self::SCRIPT_FINALIZE,
            [
                Key::uniqueState($uniqueId),
                Key::uniqueDeferred($uniqueId)
            ],
            [
                self::STATE_RUNNING
            ]
        );
    }

    /**
     * @param string $uniqueId
     *
     * @return bool
     * @throws RedisError
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
     * @throws RedisError
     */
    public static function removeDeferred($uniqueId) {
        return !$uniqueId
            || Resque::redis()->del(Key::uniqueDeferred($uniqueId));
    }

    /**
     * @param string $uniqueId
     *
     * @return bool true if the key was set, false if it did not exist
     * @throws RedisError
     */
    public static function setRunning($uniqueId) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
            || Resque::redis()->set(Key::uniqueState($uniqueId), self::makeState(self::STATE_RUNNING), ['XX']);
    }

    /**
     * @param string $uniqueId
     *
     * @throws RedisError
     */
    private static function clearIfOld($uniqueId) {
        $state = self::getState($uniqueId);

        if (!$state || $state->stateName !== self::STATE_RUNNING) {
            return;
        }

        if ((int)$state->startTime < (time() - 3600)) {
            self::clearUniqueKey($uniqueId);
        }
    }

    /**
     * @param string $uniqueId
     *
     * @throws RedisError
     */
    private static function clearUniqueKey($uniqueId) {
        $workerKeys = Resque::redis()->keys(Key::workerRuntimeInfo('*'));

        foreach ($workerKeys as $key) {
            $workerId = explode(':', $key)[2] ?? null;
            if (!$workerId) {
                continue;
            }

            $runningUniqueId = WorkerImage::load($workerId)->getRuntimeInfo()->uniqueId;
            if ($uniqueId === $runningUniqueId) {
                Log::warning('Long running unique job', [
                    'unique_id' => $uniqueId
                ]);

                return;
            }
        }

        Log::warning('Clearing stale unique key.', [
            'unique_id' => $uniqueId
        ]);
        self::removeAll($uniqueId);
    }

    /**
     * @param string $uniqueId
     *
     * @return null|UniqueState
     * @throws RedisError
     */
    private static function getState($uniqueId) {
        $stateString = Resque::redis()->get(Key::uniqueState($uniqueId));
        if (!$stateString) {
            return null;
        }

        return UniqueState::fromString($stateString);
    }

    private static function makeState(string $stateName) {
        return (new UniqueState($stateName))->toString();
    }
}