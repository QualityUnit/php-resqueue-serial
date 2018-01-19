<?php

namespace Resque\Pool;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Process\AbstractProcess;
use Resque\StatsD;

class JobAllocatorProcess extends AbstractProcess {

    const BLOCKING_TIMEOUT = 3;

    /** @var string */
    private $bufferKey;

    /**
     * @param $number
     */
    public function __construct($number) {
        parent::__construct('job-allocator', AllocatorImage::create($number));

        $this->bufferKey = Key::localJobAllocatorBuffer($number);
    }

    public function deinit() {
        Resque::redis()->sRem(Key::localJobAllocatorProcesses(), $this->getImage()->getId());
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

    public function init() {
        Resque::redis()->sAdd(Key::localJobAllocatorProcesses(), $this->getImage()->getId());
    }

    public function load() {
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
    }

    /**
     * @param $payload
     *
     * @throws Resque\Api\RedisError if processing failed
     */
    private function processPayload($payload) {
        $decoded = json_decode($payload, true);
        if (!\is_array($decoded)) {
            Resque\Log::critical('Failed to process unassigned job.', [
                'payload' => $payload
            ]);

            Resque::redis()->lRem($this->bufferKey, 1, $payload);
            return;
        }

        try {
            $queuedJob = QueuedJob::fromArray($decoded);
        } catch (\InvalidArgumentException $e) {
            Resque\Log::error('Failed to create job from payload.', ['exception' => $e]);

            Resque::redis()->lRem($this->bufferKey, 1, $payload);
            return;
        }

        $targetKey = $this->resolveTargetQueueKey($queuedJob);

        $enqueuedPayload = Resque::redis()->rPoplPush($this->bufferKey, $targetKey);
        if($payload !== $enqueuedPayload) {
            Resque\Log::critical('Enqueued payload does not match processed payload');
            exit(0);
        }
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return string
     */
    private function resolveTargetQueueKey(QueuedJob $queuedJob) {
    }
}