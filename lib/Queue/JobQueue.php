<?php


namespace Resque\Queue;


use Resque\Job\JobParseException;
use Resque\Job\QueuedJob;
use Resque\Log;
use Resque\Protocol\Job;
use Resque\RedisError;
use Resque\Resque;

class JobQueue extends Queue {

    /**
     * @return QueuedJob|null payload
     * @throws RedisError
     * @throws JobParseException
     */
    public function popJob() {
        return $this->decodeJob($this->pop());
    }

    /**
     * @param Queue $destinationQueue
     *
     * @return null|QueuedJob
     * @throws RedisError
     * @throws JobParseException
     */
    public function popJobInto(Queue $destinationQueue) {
        return $this->decodeJob($this->popInto($destinationQueue));
    }

    /**
     * @param Queue $destinationQueue
     * @param int $timeout Timeout in seconds
     *
     * @return QueuedJob|null
     * @throws RedisError
     * @throws JobParseException
     */
    public function popJobIntoBlocking(Queue $destinationQueue, $timeout) {
        return $this->decodeJob($this->popIntoBlocking($destinationQueue, $timeout));
    }

    /**
     * @param Job $job
     *
     * @return QueuedJob
     * @throws RedisError
     */
    public function pushJob(Job $job) {
        $queuedJob = new QueuedJob($job, Resque::generateJobId());

        $this->push($queuedJob->toString());

        return $queuedJob;
    }

    /**
     * @param string $payload
     *
     * @return null|QueuedJob
     * @throws JobParseException
     */
    private function decodeJob($payload) {
        if ($payload === null) {
            return null;
        }

        try {
            return QueuedJob::decode($payload);
        } catch (\InvalidArgumentException $e) {
            Log::error('Payload data corrupted on dequeue.', ['raw_payload' => $payload]);
            return null;
        }
    }
}