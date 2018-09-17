<?php


namespace Resque\Job;


use Resque\Queue\JobQueue;
use Resque\RedisError;

interface IJobSource {

    /**
     * @return QueuedJob|null next job or null if source is empty
     * @throws RedisError
     */
    public function bufferNextJob();

    /**
     * @return JobQueue
     */
    public function getBuffer();
}