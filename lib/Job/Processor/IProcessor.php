<?php


namespace Resque\Job\Processor;


use Resque\Job\RunningJob;

interface IProcessor {

    /**
     * Performs the job.
     *
     * @param RunningJob $runningJob
     */
    public function process(RunningJob $runningJob);

}