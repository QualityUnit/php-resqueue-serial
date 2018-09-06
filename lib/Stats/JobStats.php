<?php

namespace Resque\Stats;

use Resque\Job\RunningJob;
use Resque\SingletonTrait;

class JobStats {

    use SingletonTrait;

    /**
     * Reports the time it takes to process a job (in ms)
     *
     * @param RunningJob $job
     * @param int $duration in ms
     */
    public function reportDuration(RunningJob $job, int $duration) {
        Stats::global()->timing("job.{$job->getName()}.time", $duration);
    }

    /**
     * Reports the number of failed jobs
     *
     * @param RunningJob $job
     */
    public function reportFail(RunningJob $job) {
        Stats::global()->forSource($job->getJob()->getSourceId())
            ->increment("job.{$job->getName()}.fail");
    }

    /**
     * Reports the number of retried jobs
     *
     * @param RunningJob $job
     */
    public function reportReschedule(RunningJob $job) {
        Stats::global()->forSource($job->getJob()->getSourceId())
            ->increment("job.{$job->getName()}.reschedule");
    }

    /**
     * Reports the number of retried jobs
     *
     * @param RunningJob $job
     */
    public function reportRetry(RunningJob $job) {
        Stats::global()->forSource($job->getJob()->getSourceId())
            ->increment("job.{$job->getName()}.retry");
    }

    /**
     * Reports the number of processed jobs
     *
     * @param RunningJob $job
     */
    public function reportJobProcessing(RunningJob $job) {
        Stats::global()->forSource($job->getJob()->getSourceId())
            ->increment("job.{$job->getName()}.processed");
    }
}