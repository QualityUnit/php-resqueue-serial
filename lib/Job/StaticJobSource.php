<?php


namespace Resque\Job;


use Resque\Queue\JobQueue;

class StaticJobSource implements IJobSource {

    /** @var JobQueue */
    private $jobQueue;
    /** @var JobQueue */
    private $buffer;

    public function __construct(JobQueue $jobQueue, JobQueue $buffer) {
        $this->jobQueue = $jobQueue;
        $this->buffer = $buffer;
    }

    /**
     * @return QueuedJob|null next job or null if source is empty
     * @throws \Resque\RedisError
     * @throws JobParseException
     */
    public function bufferNextJob() {
        return $this->jobQueue->popJobIntoBlocking($this->buffer, 3);
    }

    /**
     * @return JobQueue
     */
    public function getBuffer() {
        return $this->buffer;
    }
}