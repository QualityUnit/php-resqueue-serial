<?php


namespace Resque\Job\Reservations;


use Resque\Job\IJobSource;
use Resque\Log;
use Resque\Process;

class SleepStrategy implements IStrategy {

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
        Log::debug("Looking for job in {$source->toString()}");
        Process::setTitle("Waiting for {$source->toString()} with interval {$this->interval}");
        $job = $source->popNonBlocking();

        if (!$job) {
            // If no job was found, we sleep for $interval before continuing and checking again
            Log::debug("Sleeping for {$this->interval}s");
            Process::setTitle("Waiting for {$source->toString()}");

            usleep($this->interval * 1000000);

            throw new WaitException();
        }

        return $job;
    }
}