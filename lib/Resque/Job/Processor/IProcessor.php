<?php


namespace Resque\Job\Processor;


use Resque\Job\RunningJob;

interface IProcessor {

    /**
     * Performs the job.
     *
     * @param RunningJob $runningJob
     */
    function process(RunningJob $runningJob);

}