<?php


namespace Resque\Queue;


use Resque\Api\Job;
use Resque\Api\RedisError;
use Resque\Job\QueuedJob;
use Resque\Log;
use Resque\Resque;

class JobQueue implements IQueue {

    /** @var BaseQueue */
    private $queue;

    public function __construct($key) {
        $this->queue = new BaseQueue($key);
    }

    public function getKey() {
        return $this->queue->getKey();
    }

    /**
     * @return QueuedJob|null payload
     * @throws RedisError
     */
    public function pop() {
        return $this->decodeJob($this->queue->pop());
    }

    /**
     * @param int $timeout Timeout in seconds
     *
     * @return QueuedJob|null payload
     * @throws RedisError
     */
    public function popBlocking($timeout) {
        return $this->decodeJob($this->queue->popBlocking($timeout));
    }

    /**
     * @param IQueue $destinationQueue
     *
     * @return null|QueuedJob
     * @throws RedisError
     */
    public function popInto(IQueue $destinationQueue) {
        return $this->decodeJob($this->queue->popInto($destinationQueue));
    }

    /**
     * @param IQueue $destinationQueue
     * @param int $timeout Timeout in seconds
     *
     * @return QueuedJob|null
     * @throws RedisError
     */
    public function popIntoBlocking(IQueue $destinationQueue, $timeout) {
        return $this->decodeJob($this->queue->popIntoBlocking($destinationQueue, $timeout));
    }

    /**
     * @param Job $payload
     *
     * @return QueuedJob
     * @throws RedisError
     */
    public function push($payload) {
        $queuedJob = new QueuedJob($payload, Resque::generateJobId());

        $this->queue->push($queuedJob->toString());

        return $queuedJob;
    }

    private function decodeJob($payload) {
        if ($payload === null) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            Log::error('Payload data corrupted on dequeue.', ['payload' => $payload]);
            return null;
        }

        Log::debug('Job retrieved from queue.', ['payload' => $payload]);

        return QueuedJob::fromArray($data);
    }
}