<?php

use Resque\Api\Job;
use Resque\Api\JobDescriptor;
use Resque\Queue\Queue;
use Resque\Redis;
use Resque\Scheduler\PlannedScheduler;
use Resque\Scheduler\SchedulerProcess;

class Resque {

    const VERSION_PREFIX = 'resque-v3';

    /** @var Redis Instance of Resque_Redis that talks to redis. */
    private static $redis;
    /**
     * @var mixed Host/port combination separated by a colon, or a nested array of server switch
     *         host/port pairs
     */
    private static $redisServer;

    public static function enqueue($queue, JobDescriptor $job) {
        return self::jobEnqueue(Job::fromJobDescriptor($job)->setQueue($queue), true);
    }

    public static function enqueueDelayed($delay, $queue, JobDescriptor $job) {
        self::jobEnqueueDelayed($delay, Job::fromJobDescriptor($job)->setQueue($queue), true);
    }

    public static function generateJobId() {
        return md5(uniqid('', true));
    }

    public static function jobEnqueue(Job $job, $checkUnique) {
        return Queue::push($job, $checkUnique)->getId();
    }

    public static function jobEnqueueDelayed($delay, Job $job, $checkUnique) {
        SchedulerProcess::schedule(time() + $delay, $job, $checkUnique);
    }

    public static function planCreate(\DateTime $startDate, \DateInterval $recurrencePeriod, $queue, JobDescriptor $job) {
        if ($recurrencePeriod->invert === 1) {
            throw new Exception('Expected positive recurrence period');
        }

        return PlannedScheduler::insertJob($startDate, $recurrencePeriod, Job::fromJobDescriptor($job)->setQueue($queue));
    }

    public static function planRemove($id) {
        return PlannedScheduler::removeJob($id);
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