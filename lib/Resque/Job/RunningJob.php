<?php

namespace Resque\Job;

use Resque;
use Resque\Api\Job;
use Resque\Api\JobDescriptor;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Worker\WorkerBase;

class RunningJob {

    /** @var string */
    private $id;
    /** @var WorkerBase */
    private $worker;
    /** @var Job */
    private $job;
    /** @var float */
    private $startTime;
    /** @var JobStatus */
    private $status;

    /**
     * @param WorkerBase $worker
     * @param QueuedJob $queuedJob
     */
    public function __construct(WorkerBase $worker, QueuedJob $queuedJob) {
        $this->id = $queuedJob->getId();
        $this->worker = $worker;
        $this->job = $queuedJob->getJob();
        $this->startTime = microtime(true);
        $this->status = new JobStatus($this->getJob(), $this->getId());
        $this->status->setRunning();
    }

    public function fail(\Throwable $t) {
        $this->reportFail($t);
        $this->status->setFailed();
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return Job
     */
    public function getJob() {
        return $this->job;
    }

    /**
     * @return float
     */
    public function getStartTime() {
        return $this->startTime;
    }

    /**
     * @return WorkerBase
     */
    public function getWorker() {
        return $this->worker;
    }

    public function reschedule(JobDescriptor $descriptor) {
        Resque::jobEnqueue($this->getJobToReschedule($descriptor), false);
        $this->status->setFinished();
        $this->reportSuccess();
    }

    public function rescheduleDelayed(JobDescriptor $descriptor, $in) {
        Resque::jobEnqueueDelayed($in,
            $this->getJobToReschedule($descriptor), false);
        $this->status->setFinished();
        $this->reportSuccess();
    }

    public function retry(\Exception $e) {
        if ($this->job->getFailCount() >= (GlobalConfig::getInstance()->getMaxTaskFails() - 1)) {
            $this->fail($e);

            return;
        }

        $this->job->incFailCount();

        $newJobId = Resque::jobEnqueue($this->job, false);
        $this->reportRetry($e, $newJobId);
        $this->status->setRetried();
    }

    public function success() {
        $this->status->setFinished();
        $this->reportSuccess();
    }

    /**
     * @param \Throwable $t
     * @param string $retryText
     *
     * @return mixed[]
     */
    private function createFailContext(\Throwable $t, $retryText = 'no retry') {
        return [
            'start_time' => date('Y-m-d\TH:i:s.uP', $this->startTime),
            'payload' => $this->job->toArray(),
            'exception' => $t,
            'retried_by' => $retryText
        ];
    }

    /**
     * @param JobDescriptor $descriptor
     *
     * @return Job
     */
    private function getJobToReschedule(JobDescriptor $descriptor) {
        $jobToReschedule = $this->job;
        if ($descriptor !== null) {
            $jobToReschedule = Job::fromJobDescriptor($descriptor);
            $jobToReschedule->setQueue($this->job->getQueue());
        }

        return $jobToReschedule;
    }

    /**
     * @param \Throwable $t
     */
    private function reportFail(\Throwable $t) {
        Log::error('Job failed.', $this->createFailContext($t));
        $this->worker->getStats()->incFailed();
    }

    /**
     * @param \Exception $e
     * @param string $retryJobId
     */
    private function reportRetry(\Exception $e, $retryJobId) {
        Log::error('Job was retried.', $this->createFailContext($e, $retryJobId));
        $this->worker->getStats()->incRetried();
    }

    private function reportSuccess() {
        $duration = floor((microtime(true) - $this->startTime) * 1000);
        if ($duration > 0) {
            $this->worker->getStats()->incProcessingTime($duration);
        }

        $this->worker->getStats()->incProcessed();
    }
}
