<?php


namespace Resque\Stats;


use Resque;
use Resque\Stats;
use Resque\StatsD;

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
        NodeStats::instance()->incDequeued();
        $this->incStat('dequeued');
    }

    public function incFailed() {
        NodeStats::instance()->incFailed();
        $this->incStat('failed');
    }

    public function incProcessed() {
        NodeStats::instance()->incProcessed();
        $this->incStat('processed');
    }

    public function incProcessingTime($byMilliseconds) {
        NodeStats::instance()->incProcessingTime($byMilliseconds);
        $this->timing('processing_time', $byMilliseconds);
    }

    public function incQueueTime($byMilliseconds) {
        NodeStats::instance()->incQueueTime($byMilliseconds);
        $this->timing('queue_time', $byMilliseconds);
    }

    public function incRetried() {
        NodeStats::instance()->incRetried();
        $this->incStat('retries');
    }

    /**
     * @param string $stat
     */
    private function incStat($stat) {
        StatsD::increment($this->key($stat));
    }

    /**
     * @param string $stat
     *
     * @return string
     */
    private function key($stat) {
        return gethostname() . ".{$this->queueName}.$stat";
    }

    /**
     * @param string $stat
     * @param int $value
     */
    private function timing($stat, $value) {
        StatsD::timing($this->key($stat), $value);
    }
}