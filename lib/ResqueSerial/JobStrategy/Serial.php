<?php


namespace ResqueSerial\JobStrategy;


use Resque_Job;
use Resque_JobStrategy_InProcess;
use ResqueSerial\ForkException;
use ResqueSerial\Worker;
use RuntimeException;

class Serial extends Resque_JobStrategy_InProcess {

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
     * @param Resque_Job $job
     * @throws ForkException
     */
    function perform(Resque_Job $job) {
        try {
            $child = $this->fork();
        } catch (RuntimeException $e) {
            throw new ForkException();
        }

        // Forked and we're the child. Run the job.
        if ($child === 0) {

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

    /**
     * Attempt to fork a child process from the parent to run a job in.
     *
     * Return values are those of pcntl_fork().
     *
     * @return int 0 for the forked child, or the PID of the child for the parent.
     * @throws RuntimeException When pcntl_fork returns -1
     */
    private function fork()
    {
        $pid = pcntl_fork();
        if($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }
}