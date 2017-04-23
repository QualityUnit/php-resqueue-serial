<?php

namespace ResqueSerial\JobStrategy;

use Resque_Worker;
use ResqueSerial\ResqueJob;

/**
 * Interface that all job strategy backends should implement.
 *
 * @package        Resque/JobStrategy
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @author        Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
interface IJobStrategy {
    /**
     * Set the Resque_Worker instance
     *
     * @param Resque_Worker $worker
     */
    function setWorker(Resque_Worker $worker);

    /**
     * Seperates the job execution context from the worker and calls $worker->perform($job).
     *
     * @param ResqueJob $job
     */
    function perform(ResqueJob $job);

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently working
     */
    function shutdown();
}
