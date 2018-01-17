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
    public function enqueue($queue, JobDescriptor $job);

    /**
     * @param int $delay Number of seconds from now when the job should be executed.
     * @param string $queue The name of the queue to place the job in.
     * @param JobDescriptor $job
     */
    public function enqueueDelayed($delay, $queue, JobDescriptor $job);

    /**
     * @param \DateTime $startDate
     * @param \DateInterval $recurrencePeriod
     * @param string $queue
     * @param JobDescriptor $job
     * @return string Plan identifier
     */
    public function planCreate(\DateTime $startDate, \DateInterval $recurrencePeriod, $queue, JobDescriptor $job);

    /**
     * @param string $id Plan identifier
     * @return boolean
     */
    public function planRemove($id);

    /**
     * @return Redis
     */
    public function redis();

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
    public function setBackend($server, $database = 0);
}