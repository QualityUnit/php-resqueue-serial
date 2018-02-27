<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Job\JobParseException;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Resque;
use Resque\Stats\AllocatorStats;

class JobAllocatorProcess extends AbstractProcess implements IAllocatorProcess {

    const BLOCKING_TIMEOUT = 3;

    /** @var string */
    private $bufferKey;

    /**
     * @param $code
     */
    public function __construct($code) {
        parent::__construct('job-allocator', AllocatorImage::create($code));

        $this->bufferKey = Key::localAllocatorBuffer($code);
    }

    /**
     * main loop
     *
     * @throws \Resque\RedisError
     */
    public function doWork() {
        Log::debug('Retrieving job from unassigned jobs');
        $keyFrom = Key::unassigned();
        $payload = Resque::redis()->brPoplPush($keyFrom, $this->bufferKey, self::BLOCKING_TIMEOUT);
        if ($payload === false) {
            Log::debug('No jobs to allocate');
            return;
        }

        $this->processPayload($payload);
    }

    /**
     * @throws \Resque\RedisError
     */
    public function revertBuffer() {
        Log::info("Reverting allocator buffer {$this->bufferKey}");
        $keyTo = Key::unassigned();
        while (false !== Resque::redis()->rPoplPush($this->bufferKey, $keyTo)) {
            // NOOP
        }
    }

    /**
     * @throws \Resque\RedisError
     */
    protected function prepareWork() {
        while (false !== ($payload = Resque::redis()->lIndex($this->bufferKey, -1))) {
            $this->processPayload($payload);
        }
    }

    /**
     * @param $payload
     *
     * @throws \Resque\RedisError
     */
    private function clearBuffer($payload) {
        Resque::redis()->lRem($this->bufferKey, 1, $payload);
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

            $this->clearBuffer($payload);

            return;
        }

        try {
            $queuedJob = QueuedJob::fromArray($decoded);
        } catch (JobParseException $e) {
            Log::error('Failed to create job from payload.', [
                'exception' => $e,
                'payload' => $payload
            ]);

            $this->clearBuffer($payload);

            return;
        }
        Log::debug("Found job {$queuedJob->getJob()->getName()}");

        $poolName = $this->resolvePoolName($queuedJob);
        $enqueuedPayload = StaticPool::assignJob($this->bufferKey, $poolName);

        AllocatorStats::instance()->reportStaticAllocated();

        $this->validatePayload($payload, $enqueuedPayload);
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return string
     */
    private function resolvePoolName(QueuedJob $queuedJob) {
        return GlobalConfig::getInstance()->getStaticPoolMapping()->resolvePoolName(
            $queuedJob->getJob()->getSourceId(),
            $queuedJob->getJob()->getName()
        );
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