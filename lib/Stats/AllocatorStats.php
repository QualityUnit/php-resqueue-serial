<?php

namespace Resque\Stats;

class AllocatorStats extends AbstractStats {

    private static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self('allocators');
        }

        return self::$instance;
    }

    /**
     * Reports the number of allocated batches
     *
     * @param int $count Number of allocated batches
     */
    public function reportBatchAllocated($count = 1) {
        $this->inc('batch.allocated', $count);
    }

    /**
     * Reports the number of batches waiting to be allocated
     *
     * @param int $length Number of batches waiting to be allocated
     */
    public function reportBatchQueue($length) {
        $this->set('batch.queue', $length);
    }

    /**
     * Reports the number of allocated jobs
     *
     * @param int $count Number of allocated items
     */
    public function reportStaticAllocated($count = 1) {
        $this->inc('static.allocated', $count);
    }

    /**
     * Reports the number of jobs waiting to be allocated
     *
     * @param int $length Number of jobs waiting to be allocated
     */
    public function reportStaticQueue($length) {
        $this->set('static.queue', $length);
    }
}