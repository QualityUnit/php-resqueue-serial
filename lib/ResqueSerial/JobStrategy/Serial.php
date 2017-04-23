<?php


namespace ResqueSerial\JobStrategy;


use ResqueSerial\Exception\ForkException;
use ResqueSerial\Log;
use ResqueSerial\ResqueJob;
use ResqueSerial\Worker;
use RuntimeException;

class Serial extends InProcess {

    /**
     * NonBlockingFork constructor.
     *
     * @param Worker $worker
     */
    public function __construct($worker) {
        $this->worker = $worker;
    }

    /**
     * Separates the job execution context from the worker and calls $worker->perform($job).
     *
     * @param ResqueJob $job
     *
     * @throws ForkException
     */
    function perform(ResqueJob $job) {
        try {
            $child = \Resque::fork();
        } catch (RuntimeException $e) {
            throw new ForkException();
        }

        // Forked and we're the child. Run the job.
        if ($child === 0) {

            $this->worker->logger = Log::prefix(getmypid() . '-serial-task');
            parent::perform($job);
            exit(0);
        }

        // Parent process
        if($child > 0) {
            $status = 'Forked Serial manager (' . $child . ') of ' . $job->queue . ' at ' . strftime('%F %T');
            $this->worker->updateProcLine($status);
            $this->worker->logger->info($status);
        }

        
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently working
     */
    function shutdown() {
        // TODO: Implement shutdown() method.
    }
}