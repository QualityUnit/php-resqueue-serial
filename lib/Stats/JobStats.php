<?php

namespace Resque\Stats;

use Resque\Job\RunningJob;

class JobStats extends AbstractStats {

    private static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self('jobs');
        }

        return self::$instance;
    }

    /**
     * Reports the time it takes to process a job (in ms)
     *
     * @param RunningJob $job
     * @param int $duration in ms
     */
    public function reportDuration(RunningJob $job, $duration) {
        $this->timing($job->getName() . '.duration', $duration);
    }

    /**
     * Reports the number of failed jobs
     *
     * @param RunningJob $job
     */
    public function reportFail(RunningJob $job) {
        $this->inc($job->getName() . '.fail', 1);
    }

    /**
     * Reports the number of retried jobs
     *
     * @param RunningJob $job
     */
    public function reportReschedule(RunningJob $job) {
        $this->inc($job->getName() . '.reschedule', 1);
    }

    /**
     * Reports the number of retried jobs
     *
     * @param RunningJob $job
     */
    public function reportRetry(RunningJob $job) {
        $this->inc($job->getName() . '.retry', 1);
    }

    /**
     * Reports the number of successfully processed jobs
     *
     * @param RunningJob $job
     */
    public function reportSuccess(RunningJob $job) {
        $this->inc($job->getName() . '.success', 1);
    }
}