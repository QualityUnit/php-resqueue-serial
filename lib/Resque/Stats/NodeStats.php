<?php


namespace Resque\Stats;


use Resque\Config\GlobalConfig;
use Resque\Stats;
use Resque\StatsD;

class NodeStats implements Stats {

    private static $instance;

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
        $this->incStat('failed');
    }

    public function incProcessed() {
        $this->incStat('processed');
    }

    public function incProcessingTime($byMilliseconds) {
        // NOOP
    }

    public function incQueueTime($byMilliseconds) {
        // NOOP
    }

    public function incRetried() {
        $this->incStat('retries');
    }

    private function incStat($stat) {
        StatsD::increment(GlobalConfig::getInstance()->getNodeId() . ".global.$stat");
    }
}