<?php

namespace Resque\Pool;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Process\AbstractProcess;
use Resque\StatsD;

class BatchAllocatorProcess extends AbstractProcess {

    const BLOCKING_TIMEOUT = 3;

    /** @var string */
    private $bufferKey;

    /**
     * @param $number
     */
    public function __construct($number) {
        parent::__construct('batch-allocator', AllocatorImage::create($number));

        $this->bufferKey = Key::localBatchAllocatorBuffer($number);
    }

    public function deinit() {
        Resque::redis()->sRem(Key::localBatchAllocatorProcesses(), $this->getImage()->getId());
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

    public function init() {
        Resque::redis()->sAdd(Key::localBatchAllocatorProcesses(), $this->getImage()->getId());
    }

    public function load() {
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
    }

    /**
     * @param $batchId
     *
     * @throws Resque\Api\RedisError if processing failed
     */
    private function assignBatch($batchId) {
        /*
         * Resolve pool
         * Ask working unit pool set key
         * If none given push to backlog
         */
        $batch = BatchImage::fromId($batchId);
    }
}