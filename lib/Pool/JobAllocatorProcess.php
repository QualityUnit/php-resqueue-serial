<?php

namespace Resque\Pool;

use Resque\Job\JobParseException;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Queue\Queue;
use Resque\Stats\AllocatorStats;

class JobAllocatorProcess extends AbstractProcess implements IAllocatorProcess {

    const BLOCKING_TIMEOUT = 3;

    /** @var Queue */
    private $buffer;
    /** @var Queue */
    private $unassignedQueue;

    /**
     * @param string $code
     */
    public function __construct($code) {
        parent::__construct('job-allocator', AllocatorImage::create($code));

        $this->buffer = new Queue(Key::localAllocatorBuffer($code));
        $this->unassignedQueue = new Queue(Key::unassigned());
    }

    /**
     * main loop
     *
     * @throws \Resque\RedisError
     */
    public function doWork() {
        Log::debug('Retrieving job from unassigned jobs');
        $payload = $this->unassignedQueue->popIntoBlocking($this->buffer, self::BLOCKING_TIMEOUT);
        if ($payload === null) {
            Log::debug('No jobs to allocate');
            return;
        }

        $this->processPayload($payload);
    }

    /**
     * @throws \Resque\RedisError
     */
    public function revertBuffer() {
        Log::info("Reverting allocator buffer {$this->buffer->getKey()}");
        while (null !== $this->buffer->popInto($this->unassignedQueue))  {
            // NOOP
        }
    }

    /**
     * @throws \Resque\RedisError
     */
    protected function prepareWork() {
        while (null !== ($payload = $this->buffer->peek())) {
            $this->processPayload($payload);
        }
    }

    /**
     * @param $payload
     *
     * @throws \Resque\RedisError if processing failed
     */
    private function processPayload($payload) {
        $decoded = json_decode($payload, true);
        if (!\is_array($decoded)) {
            Log::critical('Failed to process unassigned job.', [
                'payload' => $payload
            ]);

            $this->buffer->remove($payload);

            return;
        }

        try {
            $queuedJob = QueuedJob::fromArray($decoded);
        } catch (JobParseException $e) {
            Log::error('Failed to create job from payload.', [
                'exception' => $e,
                'payload' => $payload
            ]);

            $this->buffer->remove($payload);

            return;
        }
        Log::debug("Found job {$queuedJob->getJob()->getName()}");

        $enqueuedPayload = StaticPool::assignJob($queuedJob, $this->buffer);

        AllocatorStats::getInstance()->reportStaticAllocated();

        $this->validatePayload($payload, $enqueuedPayload);
    }

    /**
     * @param string $expected
     * @param string $actual
     */
    private function validatePayload($expected, $actual) {
        if ($expected === $actual) {
            return;
        }

        Log::critical('Enqueued payload does not match processed payload.', [
            'payload' => $expected,
            'actual' => $actual
        ]);
        exit(0);
    }
}