<?php

namespace Resque\Pool;

use Resque;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Config\StaticPool;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;

class JobAllocatorProcess extends AbstractProcess {

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
     * @throws Resque\Api\RedisError
     */
    public function doWork() {
        $keyFrom = Key::unassigned();
        $payload = Resque::redis()->brPoplPush($keyFrom, $this->bufferKey, self::BLOCKING_TIMEOUT);
        if ($payload === false) {
            return;
        }

        $this->processPayload($payload);
    }

    /**
     * @throws Resque\Api\RedisError
     */
    public function revertBuffer() {
        $keyTo = Key::unassigned();
        while (false !== Resque::redis()->rPoplPush($this->bufferKey, $keyTo)) {
            // NOOP
        }
    }

    /**
     * @throws Resque\Api\RedisError
     */
    protected function prepareWork() {
        while (false !== ($payload = Resque::redis()->lIndex($this->bufferKey, -1))) {
            $this->processPayload($payload);
        }
    }

    /**
     * @param $payload
     *
     * @throws Resque\Api\RedisError
     */
    private function clearBuffer($payload) {
        Resque::redis()->lRem($this->bufferKey, 1, $payload);
    }

    /**
     * @param $payload
     *
     * @throws Resque\Api\RedisError if processing failed
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
        } catch (\InvalidArgumentException $e) {
            Log::error('Failed to create job from payload.', [
                'exception' => $e,
                'payload' => $payload
            ]);

            $this->clearBuffer($payload);

            return;
        }

        try {
            $enqueuedPayload = $this->resolvePool($queuedJob)->assignJob($this->bufferKey);
            $this->validatePayload($payload, $enqueuedPayload);
        } catch (ConfigException $e) {
            Log::critical('Failed to resolve pool for job.', [
                'exception' => $e,
                'payload' => $payload
            ]);
            $actualPayload = Resque::redis()->rPoplPush($this->bufferKey, Key::jobAllocationFailures());
            $this->validatePayload($payload, $actualPayload);
        }
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return StaticPool
     * @throws ConfigException
     */
    private function resolvePool(QueuedJob $queuedJob) {
        $job = $queuedJob->getJob();
        $poolName = GlobalConfig::getInstance()->getStaticPoolMapping()
            ->resolvePoolName($job->getSourceId(), $job->getName());

        return GlobalConfig::getInstance()->getStaticPoolConfig()->getPool($poolName);
    }

    /**
     * @param string $expected
     * @param string $actual
     */
    private function validatePayload($expected, $actual) {
        if ($expected !== $actual) {
            Log::critical('Enqueued payload does not match processed payload.', [
                'payload' => $expected,
                'actual' => $actual
            ]);
            exit(0);
        }
    }
}