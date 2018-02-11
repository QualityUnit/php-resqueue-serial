<?php

namespace Resque\Job\Processor;

use Resque\Config\GlobalConfig;
use Resque\Job\FailException;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process;
use Resque\Protocol\Exceptions;
use Resque\Protocol\Job;
use Resque\Protocol\UniqueList;
use Resque\Resque;

class StandardProcessor implements IProcessor {

    /**
     * @param RunningJob $runningJob
     *
     * @throws \Resque\RedisError
     */
    public function process(RunningJob $runningJob) {
        $pid = Process::fork();
        if ($pid === 0) {
            // CHILD PROCESS START
            try {
                $workerPid = $runningJob->getWorker()->getImage()->getPid();
                Log::setPrefix("$workerPid-std-proc-" . posix_getpid());
                Process::setTitlePrefix("$workerPid-std-proc");
                Process::setTitle("Processing job {$runningJob->getJob()->getClass()}");
                $this->handleChild($runningJob);
            } catch (\Throwable $t) {
                try {
                    $runningJob->fail($t);
                    UniqueList::removeAll($runningJob->getJob()->getUniqueId());
                } catch (\Throwable $r) {
                    Log::critical('Failed to properly handle job failure.', [
                        'exception' => $r,
                        'payload' => $runningJob->getJob()->toArray()
                    ]);
                }
            }
            exit(0);
            // CHILD PROCESS END
        } else {
            $exitCode = $this->waitForChild($pid);
            if ($exitCode !== 0) {
                $runningJob->fail(new FailException("Job execution failed with exit code: $exitCode"));
                UniqueList::removeAll($runningJob->getJob()->getUniqueId());
            }
        }
    }

    /**
     * @param RunningJob $runningJob
     *
     * @return mixed
     * @throws FailException
     */
    private function createTask(RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            $this->setupEnvironment($job);
            $this->includePath($job);

            if (!class_exists($job->getClass())) {
                throw new TaskCreationException("Job class {$job->getClass()} does not exist.");
            }

            if (!method_exists($job->getClass(), 'perform')) {
                throw new TaskCreationException("Job class {$job->getClass()} does not contain a perform method.");
            }

            $className = $job->getClass();
            $task = new $className;
            $task->job = $job;

            return $task;
        } catch (\Exception $e) {
            $message = 'Failed to create a task instance';
            Log::error("$message from payload.", [
                'exception' => $e,
                'payload' => $job->toArray()
            ]);
            throw new FailException($message, 0, $e);
        }
    }

    /**
     * @param \Resque\Protocol\Job $job
     *
     * @throws \InvalidArgumentException
     * @throws \Resque\Protocol\DeferredException
     * @throws \Resque\RedisError
     * @throws \Resque\Protocol\UniqueException
     */
    private function enqueueDeferred(Job $job) {
        $deferred = json_decode(UniqueList::finalize($job->getUniqueId()), true);
        if (!\is_array($deferred)) {
            return;
        }

        $deferredJob = Job::fromArray($deferred);
        $delay = $deferredJob->getUid()->getDeferralDelay();
        if ($delay > 0) {
            Resque::delayedEnqueue($delay, $deferredJob);
        } else {
            Resque::enqueue($deferredJob);
        }
    }

    /**
     * @param RunningJob $runningJob
     *
     * @throws \Resque\Protocol\DeferredException
     * @throws \Resque\RedisError
     * @throws \Resque\Protocol\UniqueException
     */
    private function handleChild(RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            Log::debug("Creating task {$job->getClass()}");
            $task = $this->createTask($runningJob);
            Log::debug("Performing task {$job->getClass()}");

            UniqueList::editState($job->getUniqueId(), UniqueList::STATE_RUNNING);

            $task->perform();
            $this->reportSuccess($runningJob);

            $this->enqueueDeferred($job);
        } catch (\Exception $e) {
            $this->handleException($runningJob, $e);
        }
    }

    /**
     * @param RunningJob $runningJob
     * @param \Exception $e
     *
     * @throws \Resque\Protocol\DeferredException
     * @throws \Resque\RedisError
     * @throws \Resque\Protocol\UniqueException
     */
    private function handleException(RunningJob $runningJob, \Exception $e) {
        if (\get_class($e) === \RuntimeException::class) {
            switch ($e->getCode()) {
                case Exceptions::CODE_RETRY:
                    UniqueList::removeAll($runningJob->getJob()->getUniqueId());
                    $runningJob->retry($e);

                    return;
                case Exceptions::CODE_RESCHEDULE:
                    $delay = json_decode($e->getMessage(), true)['delay'] ?? 0;
                    Log::debug("Rescheduling task {$runningJob->getJob()->getName()} in {$delay}s");
                    $this->rescheduleJob($runningJob, $delay);

                    return;
                default: // fall through
            }
        }

        UniqueList::removeAll($runningJob->getJob()->getUniqueId());
        $runningJob->fail($e);
    }

    private function includePath(Job $job) {
        $jobPath = ltrim(trim($job->getIncludePath()), '/\\');
        if (!$jobPath) {
            return;
        }

        $fullPath = GlobalConfig::getInstance()->getTaskIncludePath();
        $fullPath = str_replace('{sourceId}', $job->getSourceId(), $fullPath);
        $fullPath .= $jobPath;

        include_once $fullPath;
    }

    private function reportSuccess(RunningJob $runningJob) {
        try {
            $runningJob->success();
        } catch (\Exception $e) {
            Log::error('Failed to report success of a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
        }
    }

    /**
     * @param RunningJob $runningJob
     * @param int $delay
     *
     * @throws \Resque\RedisError
     * @internal param $delay
     */
    private function rescheduleJob(RunningJob $runningJob, $delay) {
        try {
            UniqueList::editState($runningJob->getJob()->getUniqueId(), UniqueList::STATE_QUEUED);
            UniqueList::removeDeferred($runningJob->getJob()->getUniqueId());
            if ($delay > 0) {
                $runningJob->rescheduleDelayed($delay);
            } else {
                $runningJob->reschedule();
            }
        } catch (\Exception $e) {
            Log::critical('Failed to reschedule a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
            UniqueList::removeAll($runningJob->getJob()->getUniqueId());
        }
    }

    private function setupEnvironment(Job $job) {
        $env = $job->getEnvironment();
        if (\is_array($env)) {
            foreach ($env as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * @param $pid
     *
     * @return int
     */
    private function waitForChild($pid) {
        $status = "Forked $pid at " . strftime('%F %T');
        Process::setTitle($status);
        Log::info($status);

        return Process::waitForPid($pid);
    }

}