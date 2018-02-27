<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Resque;
use Resque\Stats\AllocatorStats;

class BatchAllocatorProcess extends AbstractProcess implements IAllocatorProcess {

    const BLOCKING_TIMEOUT = 3;

    /** @var string */
    private $bufferKey;

    /**
     * @param $code
     */
    public function __construct($code) {
        parent::__construct('batch-allocator', AllocatorImage::create($code));

        $this->bufferKey = Key::localAllocatorBuffer($code);
    }

    /**
     * main loop
     *
     * @throws PoolStateException
     * @throws \Resque\RedisError
     */
    public function doWork() {
        Log::debug('Retrieving batch from committed batches');
        $keyFrom = Key::committedBatchList();
        $batchId = Resque::redis()->brPoplPush($keyFrom, $this->bufferKey, self::BLOCKING_TIMEOUT);
        if ($batchId === false) {
            Log::debug('No batches to allocate');
            return;
        }

        $this->assignBatch($batchId);
    }

    /**
     * @throws \Resque\RedisError
     */
    public function revertBuffer() {
        Log::info("Reverting allocator buffer {$this->bufferKey}");
        $keyTo = Key::committedBatchList();
        while (false !== Resque::redis()->rPoplPush($this->bufferKey, $keyTo)) {
            // NOOP
        }
    }

    /**
     * @throws \Resque\RedisError
     */
    protected function prepareWork() {
        while (false !== ($batchId = Resque::redis()->lIndex($this->bufferKey, -1))) {
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
            BatchPool::assignBatch($batch, $this->resolvePoolName($batch));

            AllocatorStats::instance()->reportBatchAllocated();

            Resque::redis()->lRem($this->bufferKey, 1, $batchId);
        } catch (\Exception $e) {
            Log::critical("Failed to allocate batch $batchId to pool.", [
                'exception' => $e
            ]);
            $actualBatchId = Resque::redis()->rPoplPush($this->bufferKey, Key::batchAllocationFailures());
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