<?php

namespace Resque\Job;

use Resque;
use Resque\Key;

class QueuedJob {

    /** @var string */
    private $id;
    /** @var float */
    private $queuedTime;
    /** @var Job */
    private $job;

    /**
     * @param Job $job
     * @param string $id
     */
    public function __construct(Job $job, $id) {
        $this->job = $job;
        $this->id = $id;
        $this->queuedTime = microtime(true);
    }

    public static function fromArray(array $array) {
        $job = Job::fromArray($array);
        $queuedJob = new QueuedJob($job, $array['id']);
        $queuedJob->queuedTime = $array['queue_time'];

        return $queuedJob;
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
    public function getQueuedTime() {
        return $this->queuedTime;
    }

    public function reportQueued() {
        //TODO: Should this be here? Do we want also report dequeue status, or running from RunningJob is enough?
        $status = new JobStatus($this->getJob(), $this->getId());
        $status->setWaiting();
    }

    public function toArray() {
        $array = $this->job->toArray();
        $array['id'] = $this->id;
        $array['queue_time'] = $this->queuedTime;

        return $array;
    }

    public function toString() {
        return json_encode($this->toArray());
    }
}
