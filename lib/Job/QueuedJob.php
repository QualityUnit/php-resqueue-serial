<?php

namespace Resque\Job;

use Resque\Protocol\Job;

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

    /**
     * @param $payload
     *
     * @return QueuedJob
     * @throws JobParseException
     * @throws \InvalidArgumentException
     */
    public static function decode($payload) {
        $data = json_decode($payload, true);
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Payload is not json object.');
        }

        return self::fromArray($data);
    }

    /**
     * @param array $array
     *
     * @return QueuedJob
     * @throws JobParseException
     */
    public static function fromArray(array $array) {
        if (!isset($array['id'], $array['queue_time'])) {
            throw new JobParseException($array);
        }

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
