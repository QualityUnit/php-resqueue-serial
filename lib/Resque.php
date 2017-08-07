<?php


use Resque\Api\JobDescriptor;
use Resque\Api\ResqueApi;
use Resque\Api\UniqueException;
use Resque\Redis;
use Resque\ResqueImpl;

class Resque {

    const VERSION = '2.0';

    /** @var ResqueApi */
    private static $instance;

    /**
     * @param string $queue The name of the queue to place the job in.
     * @param JobDescriptor $job
     *
     * @return string Job ID when the job was created
     * @throws UniqueException
     */
    public static function enqueue($queue, JobDescriptor $job) {
        return self::getInstance()->enqueue($queue, $job);
    }

    /**
     * @param int $in Number of seconds from now when the job should be executed.
     * @param string $queue The name of the queue to place the job in.
     * @param JobDescriptor $job
     */
    public static function enqueueIn($in, $queue, JobDescriptor $job) {
        self::getInstance()->enqueueDelayed($in, $queue, $job);
    }

    /**
     * @return ResqueApi
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new ResqueImpl();
        }

        return self::$instance;
    }

    /**
     * @return Redis
     */
    public static function redis() {
        return self::getInstance()->redis();
    }

    /**
     * Given a host/port combination separated by a colon, set it as
     * the redis server that Resque will talk to.
     *
     * @param mixed $server Host/port combination separated by a colon, DSN-formatted URI, or
     *                      a callable that receives the configured database ID
     *                      and returns a Resque_Redis instance, or
     *                      a nested array of servers with host/port pairs.
     * @param int $database
     */
    public static function setBackend($server, $database = 0) {
        self::getInstance()->setBackend($server, $database);
    }
}