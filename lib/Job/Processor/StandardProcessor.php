<?php

namespace Resque\Job\Processor;

use Resque\Config\GlobalConfig;
use Resque\Job\FailException;
use Resque\Job\JobParseException;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process;
use Resque\Process\SignalTracker;
use Resque\Protocol\Exceptions;
use Resque\Protocol\Job;
use Resque\Protocol\UniqueList;
use Resque\RedisError;
use Resque\Resque;

class StandardProcessor implements IProcessor {

    const CHILD_SIGNAL_TIMEOUT = 5;
    const SIGNAL_SUCCESS = SIGUSR2;

    /** @var SignalTracker */
    private $successTracker;

    public function __construct() {
        $this->successTracker = new SignalTracker(self::SIGNAL_SUCCESS);
    }

    /**
     * @param RunningJob $runningJob
     *
     * @throws \Resque\RedisError
     */
    public function process(RunningJob $runningJob) {
        $this->successTracker->register();

        $pid = Process::fork();
        if ($pid === 0) {
            // CHILD PROCESS START
            try {
                $workerPid = $runningJob->getWorker()->getImage()->getPid();
                Log::setPrefix("$workerPid-std-proc-" . posix_getpid());
                Process::setTitlePrefix("$workerPid-std-proc");
                Process::setTitle("Processing job {$runningJob->getName()}");
                $this->handleChild($runningJob);
                Process::signal(self::SIGNAL_SUCCESS, $workerPid);
            } catch (\Throwable $t) {
                try {
                    $runningJob->fail($t);
                    UniqueList::removeAll($runningJob->getJob()->getUniqueId());
                    Process::signal(self::SIGNAL_SUCCESS, $workerPid ?? 0);
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
            try {
                $exitCode = $this->waitForChildProcess($pid, $runningJob);
                if ($exitCode !== 0) {
                    Log::warning("Job process signalled success, but exited with code $exitCode.", [
                        'payload' => $runningJob->getJob()->toArray()
                    ]);
                }
            } catch (\Exception $e) {
                $runningJob->fail(new FailException("Job execution failed: {$e->getMessage()}"));
                UniqueList::removeAll($runningJob->getJob()->getUniqueId());
            } finally {
                $this->successTracker->unregister();
            }
        }
    }

    /**
     * @param RunningJob $runningJob
     *
     * @return mixed
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
            $task->args = $job->getArgs();

            return $task;
        } catch (\Exception $e) {
            $message = 'Failed to create a task instance';
            Log::error("$message from payload.", [
                'exception' => $e,
                'payload' => $job->toArray()
            ]);
            throw new \RuntimeException($message, Exceptions::CODE_RETRY);
        }
    }

    /**
     * @param \Resque\Protocol\Job $job
     *
     * @throws JobParseException
     * @throws RedisError
     */
    private function enqueueDeferred(Job $job) {
        $payload = UniqueList::finalize($job->getUniqueId());
        if ($payload === 1) {
            return;
        }

        $deferred = json_decode($payload, true);
        if (!\is_array($deferred)) {
            Log::error('Unexpected deferred payload.', [
                'raw_payload' => $payload
            ]);

            return;
        }


        $deferredJob = Job::fromArray($deferred);
        Log::debug("Enqueuing deferred job {$deferredJob->getName()}", [
            'payload' => $deferredJob->toString()
        ]);
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
     * @throws \Resque\RedisError
     */
    private function handleChild(RunningJob $runningJob) {
        $job = $runningJob->getJob();
        try {
            Log::debug("Creating task {$job->getClass()}");
            $task = $this->createTask($runningJob);
            Log::debug("Performing task {$job->getClass()}");

            UniqueList::editState($job->getUniqueId(), UniqueList::STATE_RUNNING);

            $task->perform();
        } catch (\Exception $e) {
            $this->handleException($runningJob, $e);

            return;
        }

        $this->reportSuccess($runningJob);

        try {
            $this->enqueueDeferred($job);
        } catch (JobParseException $e) {
            Log::error('Failed to enqueue deferred job.', [
                'exception' => $e,
                'payload' => $e->getPayload()
            ]);
        }
    }

    /**
     * @param RunningJob $runningJob
     * @param \Exception $e
     *
     * @throws \Resque\RedisError
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
                    Log::debug("Rescheduling task {$runningJob->getName()} in {$delay}s");
                    $this->rescheduleJob($runningJob, $delay);

                    return;
                default: // fall through
            }
        }

        UniqueList::removeAll($runningJob->getJob()->getUniqueId());
        $runningJob->fail($e);
    }

    private function includePath(Job $job) {
        $jobPath = ltrim(trim($job->getIncludePath()), DIRECTORY_SEPARATOR);
        if (!$jobPath) {
            return;
        }

        $includePath = GlobalConfig::getInstance()->getTaskIncludePath();
        $includePath = str_replace('{sourceId}', $job->getSourceId(), $includePath);
        $includePath = rtrim($includePath, DIRECTORY_SEPARATOR);
        if (is_link($includePath)) {
            $includePath = readlink($includePath);
        }

        include_once $includePath . DIRECTORY_SEPARATOR . $jobPath;
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
     * @param RunningJob $runningJob
     *
     * @return int
     * @throws \Exception
     */
    private function waitForChildProcess($pid, $runningJob) {
        $status = "Forked $pid at " . strftime('%F %T');
        Process::setTitle($status);
        Log::info($status);

        if (!$this->successTracker->receivedFrom($pid)) {
            while (!$this->successTracker->waitFor($pid, self::CHILD_SIGNAL_TIMEOUT)) {
                // NOOP
                if (microtime(true) - $this->successTracker->getStartTime() > 3600) {
                    Log::warning("Sending kill signal to $pid, which is likely stuck.");
                    posix_kill($pid, SIGKILL);
                }
            }
        }
        $this->successTracker->unregister();

        return Process::waitForPid($pid);
    }
}