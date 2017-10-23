<?php


namespace Resque\Api;


abstract class JobDescriptor {

    /**
     * Task arguments, will be passed to task instance upon execution
     *
     * @return mixed[]
     */
    abstract function getArgs();

    /**
     * Task class
     *
     * @return string
     */
    abstract function getClass();

    /**
     * Uniquely identifies job, no other job with the same ID can be enqueued while existing did
     * not finish.
     * Unique jobs can be deferred by having specified deferral delay. Trying to queue a deferred
     * unique job with unique ID of an already running job will result in deferral of queuing of the
     * job until after the end of execution of the currently running job.
     *
     * @return JobUid|null job identifier for unique jobs, otherwise null
     */
    public function getUid() {
        return null;
    }

    /**
     * Turns on job tracking for 24h.
     *
     * @return bool TRUE to track; otherwise FALSE
     */
    public function isMonitored() {
        return false;
    }

    /**
     * File to include, used to bootstrap the environment of the job.
     *
     * @return string|null
     */
    public function getIncludePath() {
        return null;
    }

    /**
     * Used to replace variables in include path
     *
     * @return string[]|null
     */
    public function getPathVariables() {
        return null;
    }

    /**
     * Used to set $_SERVER environment for job
     *
     * @return string[]|null
     */
    public function getEnvironment() {
        return null;
    }
}