<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Exception;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Resque;

class BatchAllocatorProcess extends AbstractProcess {

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
     * @throws \Resque\Api\RedisError
     */
    public function doWork() {
        $keyFrom = Key::committedBatchList();
        $batchId = Resque::redis()->brPoplPush($keyFrom, $this->bufferKey, self::BLOCKING_TIMEOUT);
        if ($batchId === false) {
            return;
        }

        $this->assignBatch($batchId);
    }

    /**
     * @throws \Resque\Api\RedisError
     */
    public function revertBuffer() {
        $keyTo = Key::committedBatchList();
        while (false !== Resque::redis()->rPoplPush($this->bufferKey, $keyTo)) {
            // NOOP
        }
    }

    /**
     * @throws \Resque\Api\RedisError
     */
    protected function prepareWork() {
        while (false !== ($batchId = Resque::redis()->lIndex($this->bufferKey, -1))) {
            $this->assignBatch($batchId);
        }
    }

    /**
     * @param $batchId
     *
     * @throws \Resque\Api\RedisError
     */
    private function assignBatch($batchId) {
        $batch = BatchImage::load($batchId);

        try {
            BatchPool::assignBatch($batch, $this->resolvePoolName($batch));
            Resque::redis()->lRem($this->bufferKey, 1, $batchId);
        } catch (Exception $e) {
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