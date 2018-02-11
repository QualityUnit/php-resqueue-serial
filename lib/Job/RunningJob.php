<?php

namespace Resque\Job;

use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Protocol\Job;
use Resque\Resque;
use Resque\Worker\WorkerProcess;

class RunningJob {

    /** @var string */
    private $id;
    /** @var WorkerProcess */
    private $worker;
    /** @var Job */
    private $job;
    /** @var float */
    private $startTime;

    /**
     * @param WorkerProcess $worker
     * @param QueuedJob $queuedJob
     */
    public function __construct(WorkerProcess $worker, QueuedJob $queuedJob) {
        $this->id = $queuedJob->getId();
        $this->worker = $worker;
        $this->job = $queuedJob->getJob();
        $this->startTime = microtime(true);
    }

    public function fail(\Throwable $t) {
        $this->reportFail($t);
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
     * @return WorkerProcess
     */
    public function getWorker() {
        return $this->worker;
    }

    /**
     * @throws \Resque\RedisError
     */
    public function reschedule() {
        Resque::enqueueExisting($this->job);
        $this->reportSuccess();
    }

    /**
     * @param $in
     *
     * @throws \Resque\RedisError
     */
    public function rescheduleDelayed($in) {
        Resque::delayedEnqueueExisting($in, $this->job);
        $this->reportSuccess();
    }

    /**
     * @param \Exception $e
     *
     * @throws \Resque\RedisError
     */
    public function retry(\Exception $e) {
        if ($this->job->getFailCount() >= (GlobalConfig::getInstance()->getMaxTaskFails() - 1)) {
            $this->fail($e);

            return;
        }

        $this->job->incFailCount();

        $newJobId = Resque::enqueueExisting($this->job);
        $this->reportRetry($e, $newJobId);
    }

    public function success() {
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
     * @param \Throwable $t
     */
    private function reportFail(\Throwable $t) {
        Log::error('Job failed.', $this->createFailContext($t));
        // TODO STATS fail
    }

    /**
     * @param \Exception $e
     * @param string $retryJobId
     */
    private function reportRetry(\Exception $e, $retryJobId) {
        Log::error('Job was retried.', $this->createFailContext($e, $retryJobId));
        // TODO STATS retry
    }

    private function reportSuccess() {
        $duration = floor((microtime(true) - $this->startTime) * 1000);
        if ($duration > 0) {
            // TODO STATS processing time
        }

        // TODO STATS success
    }
}
