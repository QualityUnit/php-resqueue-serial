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
     * KEYS [ STATE KEY, SOURCE KEY, DESTINATION KEY, DEFERRAL KEY ]
     * ARGS [ QUEUED STATE, RUNNING STATE PREFIX, IS DEFERRABLE ]
     */
    const SCRIPT_ASSIGN_UNIQUE = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if not state then
    redis.call('SET', KEYS[1], ARGV[1])
    return redis.call('RPOPLPUSH', KEYS[2], KEYS[3])
end
if '' == ARGV[3] or 1 ~= string.find(state, ARGV[2]) then
    return false
end
local payload = redis.call('RPOP', KEYS[2])
redis.call('SETNX', KEYS[4], payload)
return payload
LUA;
    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE PREFIX ]
     *
     * RETURN payload -> correct state, deferred exists
     *        false -> correct state, deferred does not exist
     *        1 -> unique key does not exist
     *        2 -> unique key not in correct state
     */
    const SCRIPT_FINALIZE = /** @lang Lua */
        <<<LUA
local state = redis.call('GET', KEYS[1])
if not state then return 1 end
if 1 ~= string.find(state, ARGV[1]) then return 2 end
local deferred = redis.call('GET', KEYS[2])
redis.call('DEL', KEYS[1], KEYS[2])
return deferred
LUA;

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    /**
     * @param string $uniqueId
     * @param string $sourceKey
     * @param string $destinationKey
     * @param bool $deferrable
     *
     * @return false|string
     * @throws RedisError
     */
    public static function add($uniqueId, $sourceKey, $destinationKey, $deferrable) {
        self::clearIfOld($uniqueId);

        return Resque::redis()->eval(
            self::SCRIPT_ASSIGN_UNIQUE,
            [
                Key::uniqueState($uniqueId),
                $sourceKey,
                $destinationKey,
                Key::uniqueDeferred($uniqueId)
            ],
            [
                self::makeState(self::STATE_QUEUED),
                self::STATE_RUNNING,
                $deferrable
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
            throw new \InvalidArgumentException('Invalid unique ID.');
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
     * @throws RedisError
     */
    public static function removeAll($uniqueId) {
        if (!$uniqueId) {
            return;
        }

        $toDelete = [Key::uniqueState($uniqueId), Key::uniqueDeferred($uniqueId)];
        if (Resque::redis()->del($toDelete) === 0) {
            Log::warning('No unique keys to delete.', [
                'unique_id' => $uniqueId
            ]);
        }
    }

    /**
     * @param string $uniqueId
     *
     * @return bool false if the key was not set already, true on success
     * @throws \InvalidArgumentException when $uniqueId is falsy value
     * @throws RedisError
     */
    public static function setRunning($uniqueId) {
        // 1 or 0 from native redis, true or false from phpredis
        if (!$uniqueId) {
            throw new \InvalidArgumentException('Invalid unique ID.');
        }

        return (bool) Resque::redis()->set(Key::uniqueState($uniqueId), self::makeState(self::STATE_RUNNING), ['XX']);
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