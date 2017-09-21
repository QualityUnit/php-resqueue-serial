<?php


namespace Resque\Job\Processor;


use Resque\Api\RescheduleException;
use Resque\Api\RetryException;
use Resque\Config\GlobalConfig;
use Resque\Exception;
use Resque\Job\FailException;
use Resque\Job\Job;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process;
use Resque\ResqueImpl;
use Resque\Task\CreationException;
use Resque\Task\ITask;

class StandardProcessor implements IProcessor {

    public function process(RunningJob $runningJob) {
        $job = $runningJob->getJob();

        Log::debug("Creating task {$job->getClass()}");
        try {
            $task = $this->createTask($runningJob);
        } catch (FailException $e) {
            $runningJob->fail($e->getPrevious() ?: $e);

            return;
        }

        Log::debug("Performing task {$job->getClass()}");
        $pid = Process::fork();
        if ($pid === 0) {
            $this->handleChild($task, $runningJob);
        } else {
            $exitCode = $this->waitForChild($pid);
            if ($exitCode !== 0) {
                $runningJob->fail(new FailException("Job execution failed with exit code: $exitCode"));
            }
        }
    }

    private function closeRedis() {
        try {
            \Resque::redis()->quit();
        } catch (Exception $ignore) {
        }
        ResqueImpl::getInstance()->resetRedis();
    }

    private function createTask(RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            $this->includePath($job);
            $this->setupEnvironment($job);

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
            $message = "Failed to create a task instance";
            Log::error("$message from job {$job->toString()}", ['exception' => $e]);
            throw new FailException($message, 0, $e);
        }
    }

    private function includePath(Job $job) {
        $jobPath = ltrim(trim($job->getIncludePath()), '/\\');
        if(!$jobPath) {
            return;
        }

        $fullPath = GlobalConfig::getInstance()->getTaskIncludePath();
        $pathVariables = $job->getPathVariables();
        if(is_array($pathVariables)) {
            foreach ($pathVariables as $key => $value) {
                $fullPath = str_replace('{' . $key. '}', $value, $fullPath);
            }
        }

        $fullPath .= $jobPath;

        include_once $fullPath;
    }

    private function setupEnvironment(Job $job) {
        $env = $job->getEnvironment();
        if(is_array($env)) {
            foreach ($env as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * @param ITask $task
     * @param RunningJob $runningJob
     *
     * @throws RescheduleException
     */
    private function handleChild($task, RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            $task->perform();
            $this->reportSuccess($runningJob);
        } catch (RescheduleException $e) {
            Log::debug("Rescheduling task {$job->getClass()} in {$e->getDelay()}s");
            if ($e->getDelay() > 0) {
                $runningJob->rescheduleDelayed($e->getDelay());
            } else {
                $runningJob->reschedule();
            }
        } catch (RetryException $e) {
            $runningJob->retry($e);
        } catch (\Exception $e) {
            Log::error("Failed to perform job {$job->toString()}");
            $runningJob->fail($e);
        } finally {
            $this->closeRedis();
            exit(0);
        }
    }

    private function reportSuccess(RunningJob $runningJob) {
        try {
            $runningJob->success();
        } catch (Exception $e) {
            Log::error("Failed to report success of a job {$runningJob->getJob()->toString()}: {$e->getMessage()}");
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