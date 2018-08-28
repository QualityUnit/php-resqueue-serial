<?php

namespace Resque;

use Resque\Job\QueuedJob;
use Resque\Protocol\Job;
use Resque\Queue\JobQueue;
use Resque\Scheduler\DelayedScheduler;

class Resque {

    const VERSION_PREFIX = 'resqu-v5';

    /** @var Redis Instance of Resque_Redis that talks to redis. */
    private static $redis;
    /**
     * @var mixed Host/port combination separated by a colon, or a nested array of server switch host/port pairs
     */
    private static $redisServer;

    /**
     * @param int $delay Delay in seconds
     * @param \Resque\Protocol\Job $job
     *
     * @throws RedisError
     */
    public static function delayedEnqueue($delay, Job $job) {
        DelayedScheduler::schedule(time() + $delay, $job);
    }

    /**
     * @param Job $job
     *
     * @return QueuedJob
     * @throws RedisError
     */
    public static function enqueue(Job $job) {
        $unassignedQueue = new JobQueue(Key::unassigned());

        return $unassignedQueue->pushJob($job);
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
        } catch (\Exception $ignore) {
        }
        self::$redis = null;
    }

    public static function setBackend($server) {
        self::$redisServer = $server;
        self::$redis = null;
    }
}