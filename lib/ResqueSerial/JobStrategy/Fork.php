<?php

namespace ResqueSerial\JobStrategy;

use Exception;
use Resque;
use ResqueSerial\Job\DirtyExitException;
use ResqueSerial\DeprecatedWorker;
use ResqueSerial\ResqueJob;

/**
 * Seperates the job execution environment from the worker via pcntl_fork
 *
 * @package        Resque/JobStrategy
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @author        Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Fork extends InProcess {
    /**
     * @var int|null 0 for the forked child, the PID of the child for the parent, or null if no child.
     */
    protected $child;

    /**
     * @var DeprecatedWorker Instance of Resque_Worker that is starting jobs
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
     * Seperate the job from the worker via pcntl_fork
     *
     * @param ResqueJob $job
     */
    public function perform(ResqueJob $job) {
        $this->child = Resque::fork();

        // Forked and we're the child. Run the job.
        if ($this->child === 0) {
            try {
                $this->worker->unregisterSigHandlers(); // let task process use separate signal handlers
                parent::perform($job);
                Resque::resetRedis();
                exit(0);
            } catch (Exception $e) {
                $this->worker->logger->error("Failed to perform job $job", ['exception' => $e]);
            }
            exit(1);
        }

        // Parent process, sit and wait
        if ($this->child > 0) {
            $status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
            $this->worker->updateProcLine($status);
            $this->worker->logger->info($status);

            // Wait until the child process finishes before continuing
            pcntl_waitpid($this->child, $status);
            $exitStatus = pcntl_wexitstatus($status);
            if ($exitStatus !== 0) {
                $job->fail(new DirtyExitException(
                        'Job exited with exit code ' . $exitStatus
                ));
            }
        }

        $this->child = null;
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently working
     */
    public function shutdown() {
        if (!$this->child) {
            $this->worker->logger->info('No child to kill.');

            return;
        }

        $this->worker->logger->info('Killing child at ' . $this->child);
        if (exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->worker->logger->info('Killing child at ' . $this->child);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        } else {
            $this->worker->logger->info('Child ' . $this->child . ' not found, restarting.');
            $this->worker->shutdown();
        }
    }
}
