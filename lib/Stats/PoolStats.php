<?php

namespace Resque\Stats;

class PoolStats extends AbstractStats {

    private static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self('pools');
        }

        return self::$instance;
    }

    /**
     * Reports the number of processed items
     *
     * @param string $poolName
     * @param int $count
     */
    public function reportProcessed($poolName, $count = 1) {
        $this->inc($poolName . '.processed', $count);
    }

    /**
     * Reports the number of items in pool waiting to be processed
     *
     * @param string $poolName
     * @param int $length
     */
    public function reportQueue($poolName, $length) {
        $this->gauge($poolName . '.queue', $length);
    }

}