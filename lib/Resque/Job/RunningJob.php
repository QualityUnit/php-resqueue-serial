<?php

namespace Resque\Job;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\ResqueImpl;
use Resque\Stats;
use Resque\UniqueList;
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

    public function fail(\Exception $e = null) {
        $this->reportFail($e);
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

    public function reschedule() {
        ResqueImpl::getInstance()->jobEnqueue($this->job, false);
        $this->status->setFinished();
        $this->reportSuccess();
    }

    public function rescheduleDelayed($in) {
        ResqueImpl::getInstance()->jobEnqueueDelayed($in, $this->job, false);
        $this->status->setFinished();
        $this->reportSuccess();
    }

    public function retry(\Exception $e) {
        if ($this->job->getFailCount() >= (GlobalConfig::getInstance()->getMaxTaskFails() - 1)) {
            $this->fail($e);

            return;
        }

        $this->job->incFailCount();

        $newJobId = ResqueImpl::getInstance()->jobEnqueue($this->job, false);
        $this->reportFail($e, $newJobId);
        $this->status->setRetried();
    }

    public function success() {
        UniqueList::remove($this->job->getUniqueId());
        $this->status->setFinished();
        $this->reportSuccess();
    }

    /**
     * @param \Exception $e
     * @param string $retryText
     *
     * @return string
     */
    private function createFailReport(\Exception $e, $retryText) {
        $data = new \stdClass;
        $data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data->payload = $this->job->toArray();
        $data->exception = get_class($e);
        $data->error = $e->getMessage();
        $data->backtrace = explode("\n", $e->getTraceAsString());
        $data->queue = $this->job->getQueue();
        $data->retried_by = $retryText;

        return json_encode($data);
    }

    /**
     * @param $statName
     */
    private function report($statName) {
        Stats::incGlobal($statName);
        Stats::incQueue($this->job->getQueue(), $statName);
    }

    /**
     * @param \Exception $e
     * @param string $retryText
     */
    private function reportFail(\Exception $e, $retryText = null) {
        $statName = $retryText === null ? 'failed' : 'retries';
        $this->report($statName);
        Resque::redis()->rpush($statName, $this->createFailReport($e, $retryText));
    }

    private function reportSuccess() {
        $duration = floor((microtime(true) - $this->startTime) * 1000);
        if ($duration > 0) {
            Stats::incQueue($this->getJob()->getQueue(), 'processing_time', $duration);
        }

        $this->report('processed');
    }
}