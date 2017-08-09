<?php

namespace Resque;


use Resque\Api\JobDescriptor;
use Resque\Api\ResqueApi;
use Resque\Job\Job;
use Resque\Queue\Queue;
use Resque\Queue\SerialQueue;
use Resque\Scheduler\Scheduler;

class ResqueImpl implements ResqueApi {

    /** @var Redis Instance of Resque_Redis that talks to redis. */
    private $redis = null;
    /**
     * @var mixed Host/port combination separated by a colon, or a nested array of server switch
     *         host/port pairs
     */
    private $redisServer = null;
    /** @var int ID of Redis database to select. */
    private $redisDatabase = 0;

    /**
     * Internal.
     *
     * @return ResqueImpl
     */
    public static function getInstance() {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return \Resque::getInstance();
    }

    /** @inheritdoc */
    public function enqueue($queue, JobDescriptor $job) {
        return $this->jobEnqueue(Job::fromJobDescriptor($job)->setQueue($queue), true);
    }

    /** @inheritdoc */
    public function enqueueDelayed($delay, $queue, JobDescriptor $job) {
        $this->jobEnqueueDelayed($delay, Job::fromJobDescriptor($job)->setQueue($queue), true);
    }

    public function generateJobId() {
        return md5(uniqid('', true));
    }

    public function jobEnqueue(Job $job, $checkUnique) {
        if ($job->isSerial()) {
            return SerialQueue::push($job)->getId();
        }

        return Queue::push($job, $checkUnique)->getId();
    }

    public function jobEnqueueDelayed($delay, Job $job, $checkUnique) {
        Scheduler::schedule(time() + $delay, $job, $checkUnique);
    }

    /** @inheritdoc */
    public function redis() {
        if ($this->redis !== null) {
            return $this->redis;
        }

        if (is_callable($this->redisServer)) {
            $this->redis = call_user_func($this->redisServer, $this->redisDatabase);
        } else {
            $this->redis = new Redis($this->redisServer, $this->redisDatabase);
        }

        Redis::prefix(\Resque::VERSION_PREFIX);

        return $this->redis;
    }

    public function resetRedis() {
        if ($this->redis === null) {
            return;
        }
        try {
            $this->redis->close();
        } catch (Exception $ignore) {
        }
        $this->redis = null;
    }

    public function setBackend($server, $database = 0) {
        $this->redisServer = $server;
        $this->redisDatabase = $database;
        $this->redis = null;
    }
}