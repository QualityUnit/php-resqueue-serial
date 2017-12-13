<?php


namespace Resque\Stats;


use Resque;
use Resque\Key;
use Resque\Stats;

class QueueStats implements Stats {

    /** @var string */
    private $queueName;

    /**
     * @param $queueName
     */
    public function __construct($queueName) {
        $this->queueName = $queueName;
    }

    public function incDequeued() {
        GlobalStats::instance()->incDequeued();
        $this->incStat('dequeued');
    }

    public function incFailed() {
        GlobalStats::instance()->incFailed();
        $this->incStat('failed');
    }

    public function incProcessed() {
        GlobalStats::instance()->incProcessed();
        $this->incStat('processed');
    }

    public function incProcessingTime($byMilliseconds) {
        GlobalStats::instance()->incProcessingTime($byMilliseconds);
        $this->incStat('processing_time', $byMilliseconds);
    }

    public function incQueueTime($byMilliseconds) {
        GlobalStats::instance()->incQueueTime($byMilliseconds);
        $this->incStat('queue_time', $byMilliseconds);
    }

    public function incRetried() {
        GlobalStats::instance()->incRetried();
        $this->incStat('retries');
    }

    /**
     * @param string $stat
     * @param int $by
     */
    private function incStat($stat, $by = 1) {
        Resque::redis()->incrBy(Key::statsQueue($this->queueName, $stat), $by);
    }
}