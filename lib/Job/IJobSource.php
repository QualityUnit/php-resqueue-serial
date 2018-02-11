<?php


namespace Resque\Job;


use Resque\Api\RedisError;

interface IJobSource {

    /**
     * @return QueuedJob|null next job or null if source is empty
     * @throws RedisError
     */
    public function bufferNextJob();

    /**
     * @return QueuedJob|null buffered job or null if buffer is empty
     * @throws RedisError
     */
    public function bufferPop();
}