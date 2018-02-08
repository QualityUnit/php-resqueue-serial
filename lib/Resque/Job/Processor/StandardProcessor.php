<?php

namespace Resque\Job\Processor;

use Resque;
use Resque\Api\Job;
use Resque\Api\RescheduleException;
use Resque\Api\RetryException;
use Resque\Config\GlobalConfig;
use Resque\Exception;
use Resque\Job\FailException;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process;
use Resque\Task\CreationException;
use Resque\UniqueList;

class StandardProcessor implements IProcessor {

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

    private function createTask(RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            $this->setupEnvironment($job);
            $this->includePath($job);

            if (!class_exists($job->getClass())) {
                throw new CreationException("Job class {$job->getClass()} does not exist.");
            }

            if (!method_exists($job->getClass(), 'perform')) {
                throw new CreationException("Job class {$job->getClass()} does not contain a perform method.");
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

    private function enqueueDeferred(Job $job) {
        $deferred = json_decode(UniqueList::finalize($job->getUniqueId()), true);
        if (!is_array($deferred)) {
            return;
        }

        $deferredJob = Job::fromArray($deferred);
        $delay = $deferredJob->getUid()->getDeferralDelay();
        if ($delay > 0) {
            Resque::jobEnqueueDelayed($delay, $deferredJob, true);
        } else {
            Resque::jobEnqueue($deferredJob, true);
        }
    }

    /**
     * @param RunningJob $runningJob
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
        } catch (RescheduleException $e) {
            Log::debug("Rescheduling task {$job->getClass()} in {$e->getDelay()}s");

            $this->rescheduleJob($runningJob, $e);
        } catch (RetryException $e) {
            UniqueList::removeAll($job->getUniqueId());
            $runningJob->retry($e);
        } catch (\Exception $e) {
            UniqueList::removeAll($job->getUniqueId());
            $runningJob->fail($e);
        }
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
        } catch (Exception $e) {
            Log::error('Failed to report success of a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
        }
    }

    /**
     * @param RunningJob $runningJob
     * @param RescheduleException $e
     *
     * @internal param $delay
     */
    private function rescheduleJob(RunningJob $runningJob, RescheduleException $e) {
        try {
            UniqueList::editState($runningJob->getJob()->getUniqueId(), UniqueList::STATE_QUEUED);
            UniqueList::removeDeferred($runningJob->getJob()->getUniqueId());
            if ($e->getDelay() > 0) {
                $runningJob->rescheduleDelayed($e->getJobDescriptor(), $e->getDelay());
            } else {
                $runningJob->reschedule($e->getJobDescriptor());
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
        if (is_array($env)) {
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