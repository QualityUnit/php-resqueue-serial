<?php

namespace Resque\Pool;

use Resque;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process\AbstractProcess;

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
     * @throws Resque\Api\RedisError
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
     * @throws Resque\Api\RedisError
     */
    public function revertBuffer() {
        $keyTo = Key::committedBatchList();
        while (false !== Resque::redis()->rPoplPush($this->bufferKey, $keyTo)) {
            // NOOP
        }
    }

    /**
     * @throws Resque\Api\RedisError
     */
    protected function prepareWork() {
        while (false !== ($batchId = Resque::redis()->lIndex($this->bufferKey, -1))) {
            $this->assignBatch($batchId);
        }
    }

    /**
     * @param $batchId
     *
     * @throws Resque\Api\RedisError if processing failed
     */
    private function assignBatch($batchId) {
        $batch = BatchImage::load($batchId);

        try {
            $this->resolvePool($batch)->assignBatch($batch);
            Resque::redis()->lRem($this->bufferKey, 1, $batchId);
        } catch (Resque\Exception $e) {
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
     * @return BatchPool
     * @throws ConfigException
     */
    private function resolvePool(BatchImage $batch) {
        $poolName = GlobalConfig::getInstance()->getBatchPoolMapping()
            ->resolvePoolName($batch->getSourceId(), $batch->getJobName());

        return GlobalConfig::getInstance()->getBatchPoolConfig()->getPool($poolName);
    }

    /**
     * @param string $expected
     * @param string $actual
     */
    private function validatePayload($expected, $actual) {
        if ($expected !== $actual) {
            Log::critical('Enqueued payload does not match processed batch ID.', [
                'payload' => $expected,
                'actual' => $actual
            ]);
            exit(0);
        }
    }
}