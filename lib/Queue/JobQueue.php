<?php


namespace Resque\Queue;


use Resque\Job\JobParseException;
use Resque\Job\QueuedJob;
use Resque\Log;
use Resque\RedisError;
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
     * @throws JobParseException
     */
    public function pop() {
        return $this->decodeJob($this->queue->pop());
    }

    /**
     * @param int $timeout Timeout in seconds
     *
     * @return QueuedJob|null payload
     * @throws RedisError
     * @throws JobParseException
     */
    public function popBlocking($timeout) {
        return $this->decodeJob($this->queue->popBlocking($timeout));
    }

    /**
     * @param IQueue $destinationQueue
     *
     * @return null|QueuedJob
     * @throws RedisError
     * @throws JobParseException
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
     * @throws JobParseException
     */
    public function popIntoBlocking(IQueue $destinationQueue, $timeout) {
        return $this->decodeJob($this->queue->popIntoBlocking($destinationQueue, $timeout));
    }

    /**
     * @param \Resque\Protocol\Job $payload
     *
     * @return QueuedJob
     * @throws RedisError
     */
    public function push($payload) {
        $queuedJob = new QueuedJob($payload, Resque::generateJobId());

        $this->queue->push($queuedJob->toString());

        return $queuedJob;
    }

    /**
     * @param $payload
     *
     * @return null|QueuedJob
     * @throws JobParseException
     */
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