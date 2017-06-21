<?php


namespace Resque\Api;


abstract class JobDescriptor {

    /**
     * Task arguments, will be passed to task instance upon execution
     * @return mixed[]
     */
    abstract function getArgs();

    /**
     * Task class
     * @return string
     */
    abstract function getClass();

    /**
     * Used only by serial jobs, groups series into smaller serial units, that will run in parallel.
     * @return string|null
     */
    public function getSecondarySerialId() {
        return null;
    }

    /**
     * ID to uniquely identify series of jobs, that will be executed in the order they were queued.
     * @return string|null NULL for standard jobs
     */
    public function getSerialId() {
        return null;
    }

    /**
     * Uniquely identifies job, no other job with the same ID can be enqueued while existing did not finish.
     * @return string|null
     */
    public function getUniqueId() {
        return null;
    }

    /**
     * Turns on job tracking for 24h.
     * @return bool TRUE to track; otherwise FALSE
     */
    public function isMonitored() {
        return false;
    }
}