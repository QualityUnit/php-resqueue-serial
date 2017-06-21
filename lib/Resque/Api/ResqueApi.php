<?php

namespace Resque\Api;

use Resque\Redis;

interface ResqueApi {


    /**
     * @param string $queue The name of the queue to place the job in.
     * @param JobDescriptor $job
     * @return string Job ID when the job was created
     * @throws UniqueException
     */
    function enqueue($queue, JobDescriptor $job);

    /**
     * @param int $delay Number of seconds from now when the job should be executed.
     * @param string $queue The name of the queue to place the job in.
     * @param JobDescriptor $job
     */
    function enqueueDelayed($delay, $queue, JobDescriptor $job);

    /**
     * @return Redis
     */
    function redis();

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
    function setBackend($server, $database = 0);
}