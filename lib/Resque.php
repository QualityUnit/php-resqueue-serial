<?php

use Resque\Api\Job;
use Resque\Queue\Queue;
use Resque\Redis;
use Resque\Scheduler\DelayedScheduler;

class Resque {

    const VERSION_PREFIX = 'resque-v4';

    /** @var Redis Instance of Resque_Redis that talks to redis. */
    private static $redis;
    /**
     * @var mixed Host/port combination separated by a colon, or a nested array of server switch host/port pairs
     */
    private static $redisServer;

    public static function generateJobId() {
        return md5(uniqid('', true));
    }

    public static function enqueue(Job $job, $checkUnique) {
        return Queue::push($job, $checkUnique)->getId();
    }

    public static function enqueueDelayed($delay, Job $job, $checkUnique) {
        DelayedScheduler::schedule(time() + $delay, $job, $checkUnique);
    }

    public static function redis() {
        if (self::$redis !== null) {
            return self::$redis;
        }

        self::$redis = new Redis(self::$redisServer);

        Redis::prefix(\Resque::VERSION_PREFIX);

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