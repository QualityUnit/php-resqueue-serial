<?php

namespace Resque\Protocol;

use Resque\Key;
use Resque\Log;
use Resque\RedisError;
use Resque\Resque;
use Resque\Worker\WorkerImage;

class UniqueLock {

    /**
     * KEYS [ STATE KEY, SOURCE KEY, DEFERRAL KEY ]
     * ARGS [ RUNNING STATE, IS DEFERRABLE ]
     * RETURN 1 -> job was locked and is ready to be processed
     *        2 -> job was popped from source key and deferred
     *        3 -> job was popped from source and discarded
     */
    const SCRIPT_LOCK_UNIQUE = /** @lang Lua */
        <<<LUA
if not redis.call('GET', KEYS[1]) then
    redis.call('SET', KEYS[1], ARGV[1])
    return 1
end
local payload = redis.call('RPOP', KEYS[2])
if '' ~= ARGV[2] then
    redis.call('SETNX', KEYS[3], payload)
    return 2
end
return 3
LUA;

    /**
     * KEYS [ STATE KEY, DEFERRED KEY ]
     * ARGS [ RUNNING STATE PREFIX ]
     * RETURN payload -> correct state, deferred exists
     *        false -> correct state, deferred does not exist
     *        1 -> unique key does not exist
     */
    const SCRIPT_UNLOCK_UNIQUE = /** @lang Lua */
        <<<LUA
if not redis.call('GET', KEYS[1]) then return 1 end
local deferred = redis.call('GET', KEYS[2])
redis.call('DEL', KEYS[1], KEYS[2])
return deferred
LUA;

    const STATE_RUNNING = 'running';

    /**
     * @param string $uniqueId
     *
     * @throws RedisError
     */
    public static function clearLock($uniqueId) {
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
     * @param string $sourceKey
     * @param bool $deferrable
     *
     * @throws DeferredException if lock fails and a job was deferred from source
     * @throws DiscardedException if lock fails and a job was discarded from source
     * @throws RedisError
     */
    public static function lock($uniqueId, $sourceKey, $deferrable) {
        if (!$uniqueId) {
            throw new \InvalidArgumentException('Invalid unique ID.');
        }

        self::clearLockIfOld($uniqueId);

        $result = Resque::redis()->eval(
            self::SCRIPT_LOCK_UNIQUE,
            [
                Key::uniqueState($uniqueId),
                $sourceKey,
                Key::uniqueDeferred($uniqueId)
            ],
            [
                self::makeState(self::STATE_RUNNING),
                $deferrable
            ]
        );

        if ($result === 2) {
            throw new DeferredException('Source job was deferred.');
        }
        if ($result === 3) {
            throw new DiscardedException('Source job was discarded.');
        }
    }

    /**
     * @param string $uniqueId
     *
     * @return string|false deferred payload if it exists, false otherwise
     * @throws UniqueLockMissingException if there was no lock for unique id
     * @throws RedisError
     */
    public static function unlock($uniqueId) {
        if (!$uniqueId) {
            throw new \InvalidArgumentException('Invalid unique ID.');
        }

        $result = Resque::redis()->eval(
            self::SCRIPT_UNLOCK_UNIQUE,
            [
                Key::uniqueState($uniqueId),
                Key::uniqueDeferred($uniqueId)
            ],
            [
                self::STATE_RUNNING
            ]
        );

        if ($result === 1) {
            throw new UniqueLockMissingException("Unique lock for '$uniqueId'' missing.");
        }

        return $result;
    }

    /**
     * @param string $uniqueId
     *
     * @throws RedisError
     */
    private static function clearLockIfOld($uniqueId) {
        $state = self::getState($uniqueId);

        if ($state === null) {
            return;
        }

        if ((int)$state->startTime < (time() - 3600)) {
            self::clearStuckLock($uniqueId);
        }
    }

    /**
     * @param string $uniqueId
     *
     * @throws RedisError
     */
    private static function clearStuckLock($uniqueId) {
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
        self::clearLock($uniqueId);
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