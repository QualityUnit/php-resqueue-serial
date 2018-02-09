<?php

namespace Resque\Job;

use Resque\Api\RedisError;
use Resque\Pool\BatchImage;
use Resque\Queue\JobQueue;
use Resque\Resque;

class BatchJobSource implements IJobSource {


    /** @var string */
    private $batchListKey;
    /** @var JobQueue */
    private $buffer;
    /** @var callable */
    private $batchCleaner;

    /**
     * @param string $queueListKey
     * @param JobQueue $buffer
     * @param callable $batchCleaner
     */
    public function __construct(string $queueListKey, JobQueue $buffer, callable $batchCleaner) {
        $this->batchListKey = $queueListKey;
        $this->buffer = $buffer;
        $this->batchCleaner = $batchCleaner;
    }


    /**
     * @return QueuedJob|null next job or null if source is empty
     * @throws RedisError
     */
    public function bufferNextJob() {
        $batchId = Resque::redis()->brPoplPush($this->batchListKey, $this->batchListKey, 3);
        if ($batchId === false) {
            return null;
        }
        $batchImage = BatchImage::load($batchId);

        $batchQueue = new JobQueue($batchImage->getKey());
        $job = $batchQueue->popInto($this->buffer);

        if ($job !== null) {
            return $job;
        }

        \call_user_func($this->batchCleaner, $batchImage);

        return null;
    }

    /**
     * @return QueuedJob|null buffered job or null if buffer is empty
     * @throws RedisError
     */
    public function bufferPop() {
        return $this->buffer->pop();
    }
}