<?php

namespace ResqueSerial\JobStrategy;

use ResqueSerial\DeprecatedWorker;
use ResqueSerial\ResqueJob;

/**
 * Runs the job in the same process as Resque_Worker
 *
 * @package        Resque/JobStrategy
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @author        Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class InProcess implements IJobStrategy {
    /**
     * @var DeprecatedWorker
     */
    protected $worker;

    /**
     * Set the Resque_Worker instance
     *
     * @param DeprecatedWorker $worker
     */
    public function setWorker(DeprecatedWorker $worker) {
        $this->worker = $worker;
    }

    /**
     * Run the job in the worker process
     *
     * @param ResqueJob $job
     */
    public function perform(ResqueJob $job) {
        $status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
        $this->worker->updateProcLine($status);
        $this->worker->logger->info($status);
        $this->worker->perform($job);
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently working
     */
    public function shutdown() {
        $this->worker->logger->info('No child to kill.');
    }
}
