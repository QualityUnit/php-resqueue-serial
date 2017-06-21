<?php


namespace Resque\Job\Reservations;


use Resque\Job\IJobSource;
use Resque\Log;
use Resque\Process;

class BlockingStrategy implements IStrategy {

    /** @var integer */
    private $interval;

    /**
     * @param integer $interval
     */
    public function __construct($interval) {
        $this->interval = $interval;
    }

    /**
     * @inheritdoc
     */
    public function reserve(IJobSource $source) {
        Log::debug("Looking for job in {$source->toString()} with timeout {$this->interval}s");
        Process::setTitle("Waiting for {$source->toString()} with blocking timeout {$this->interval}");
        $job = $source->popBlocking($this->interval);

        if (!$job) {
            throw new WaitException();
        }

        return $job;
    }

}