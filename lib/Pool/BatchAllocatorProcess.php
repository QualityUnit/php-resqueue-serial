<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Queue\Queue;
use Resque\Resque;
use Resque\Stats\AllocatorStats;

class BatchAllocatorProcess extends AbstractProcess implements IAllocatorProcess {

    const BLOCKING_TIMEOUT = 3;
    const TTL_UNSET_RESPONSE = -1;

    /** @var Queue */
    private $buffer;
    /** @var Queue */
    private $committedQueue;
    /** @var Queue */
    private $failQueue;

    /**
     * @param $code
     */
    public function __construct($code) {
        parent::__construct('batch-allocator', AllocatorImage::create($code));

        $this->buffer = new Queue(Key::localAllocatorBuffer($code));
        $this->committedQueue = new Queue(Key::committedBatchList());
        $this->failQueue = new Queue(Key::batchAllocationFailures());
    }

    /**
     * main loop
     *
     * @throws \Resque\RedisError
     */
    public function doWork() {
        Log::debug('Retrieving batch from committed batches');
        $batchId = $this->committedQueue->popIntoBlocking($this->buffer, self::BLOCKING_TIMEOUT);
        if ($batchId === null) {
            Log::debug('No batches to allocate');
            return;
        }

        $this->assignBatch($batchId);
    }

    /**
     * @throws \Resque\RedisError
     */
    public function revertBuffer() {
        Log::info("Reverting allocator buffer {$this->buffer->getKey()}");
        while (null !== $this->buffer->popInto($this->committedQueue)) {
            // NOOP
        }
    }

    /**
     * @throws \Resque\RedisError
     */
    protected function prepareWork() {
        while (null !== ($batchId = $this->buffer->peek())) {
            $this->assignBatch($batchId);
        }
    }

    /**
     * @param $batchId
     *
     * @throws \Resque\RedisError
     */
    private function assignBatch($batchId) {
        $batch = BatchImage::load($batchId);

        try {
            if (Resque::redis()->ttl($batch->getKey()) !== self::TTL_UNSET_RESPONSE) {
                Resque::redis()->persist($batch->getKey());
                Log::error('Detected and removed TTL on committed batch key.', [
                    'batch_id' => $batch->getId()
                ]);
            }

            BatchPool::assignBatch($batch, $this->resolvePoolName($batch));

            AllocatorStats::getInstance()->reportBatchAllocated();

            $this->buffer->remove($batchId);
        } catch (\Exception $e) {
            Log::critical("Failed to allocate batch $batchId to pool.", [
                'exception' => $e
            ]);
            $actualBatchId = $this->buffer->popInto($this->failQueue);
            $this->validatePayload($batchId, $actualBatchId);
        }
    }

    /**
     * @param BatchImage $batch
     *
     * @return string
     */
    private function resolvePoolName(BatchImage $batch) {
        return GlobalConfig::getInstance()->getBatchPoolMapping()->resolvePoolName(
            $batch->getSourceId(),
            $batch->getJobName()
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
        Log::critical('Enqueued payload does not match processed batch ID.', [
            'payload' => $expected,
            'actual' => $actual
        ]);
        exit(0);
    }
}