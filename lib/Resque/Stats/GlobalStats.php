<?php


namespace Resque\Stats;


use Resque;
use Resque\Key;
use Resque\Stats;

class GlobalStats implements Stats {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function incDequeued() {
        // NOOP
    }

    public function incFailed() {
        return $this->incStat('failed');
    }

    public function incProcessed() {
        return $this->incStat('processed');
    }

    public function incProcessingTime($byMilliseconds) {
        // NOOP
    }

    public function incQueueTime($byMilliseconds) {
        // NOOP
    }

    public function incRetried() {
        return $this->incStat('retries');
    }

    private function incStat($stat) {
        return (bool)Resque::redis()->incrby(Key::statsGlobal($stat), 1);
    }
}