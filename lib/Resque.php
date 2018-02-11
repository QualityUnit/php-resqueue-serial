<?php

namespace Resque;

use Exception;
use Resque\Job\QueuedJob;
use Resque\Protocol\DeferredException;
use Resque\Protocol\Job;
use Resque\Protocol\UniqueException;
use Resque\Protocol\UniqueList;
use Resque\Queue\JobQueue;
use Resque\Scheduler\DelayedScheduler;

class Resque {

    const VERSION_PREFIX = 'resqu-v4';

    /** @var Redis Instance of Resque_Redis that talks to redis. */
    private static $redis;
    /**
     * @var mixed Host/port combination separated by a colon, or a nested array of server switch host/port pairs
     */
    private static $redisServer;

    /**
     * @param Job $job
     * @param bool $checkUnique
     *
     * @return QueuedJob
     * @throws \Resque\RedisError
     * @throws DeferredException
     * @throws UniqueException
     */
    public static function enqueue(Job $job, $checkUnique) {
        UniqueList::add($job, !$checkUnique);

        $unassignedQueue = new JobQueue(Key::unassigned());

        return $unassignedQueue->push($job);
    }

    /**
     * @param int $delay Delay in seconds
     * @param \Resque\Protocol\Job $job
     * @param bool $checkUnique
     *
     * @throws DeferredException
     * @throws UniqueException
     * @throws RedisError
     */
    public static function enqueueDelayed($delay, Job $job, $checkUnique) {
        DelayedScheduler::schedule(time() + $delay, $job, $checkUnique);
    }

    /**
     * @return string
     */
    public static function generateJobId() {
        return md5(uniqid('', true));
    }

    /**
     * @return Redis
     * @throws \Resque\RedisError
     */
    public static function redis() {
        if (self::$redis !== null) {
            return self::$redis;
        }

        self::$redis = new Redis(self::$redisServer);

        Redis::prefix(self::VERSION_PREFIX);

        return self::$redis;
    }

    public static function resetRedis() {
        if (self::$redis === null) {
            return;
        }
        try {
            self::$redis->close();
        } catch (Exception $ignore) {
        }
        self::$redis = null;
    }

    public static function setBackend($server) {
        self::$redisServer = $server;
        self::$redis = null;
    }
}