<?php


namespace Resque\Job;


use Resque\Api\JobDescriptor;

class Job {

    /** @var string */
    protected $class = null;
    /** @var array */
    protected $args = [];
    /** @var string */
    protected $queue = null;
    /** @var string */
    protected $uniqueId = null;
    /** @var string */
    protected $serialId = null;
    /** @var string */
    protected $secondarySerialId = null;
    /** @var boolean */
    protected $isMonitored = false;
    /** @var integer */
    protected $failCount = 0;

    protected function __construct() {
    }

    /**
     * @param array $array
     *
     * @return Job
     */
    public static function fromArray(array $array) {
        $job = new Job();
        $job->class = isset($array['class']) ? $array['class'] : $job->class;
        $job->args = isset($array['args']) ? $array['args'] : $job->args;
        $job->queue = isset($array['queue']) ? $array['queue'] : $job->queue;
        $job->uniqueId = isset($array['uniqueId']) ? $array['uniqueId'] : $job->uniqueId;
        $job->serialId = isset($array['serialId']) ? $array['serialId'] : $job->serialId;
        $job->secondarySerialId = isset($array['secondarySerialId']) ? $array['secondarySerialId'] : $job->secondarySerialId;
        $job->isMonitored = isset($array['isMonitored']) ? $array['isMonitored'] : $job->isMonitored;
        $job->failCount = isset($array['failCount']) ? $array['failCount'] : $job->failCount;

        return $job;
    }

    /**
     * @param JobDescriptor $jobDescriptor
     *
     * @return Job
     */
    public static function fromJobDescriptor(JobDescriptor $jobDescriptor) {
        $job = new Job();
        $job->class = $jobDescriptor->getClass();
        $job->args = $jobDescriptor->getArgs();
        $job->uniqueId = $jobDescriptor->getUniqueId();
        $job->serialId = $jobDescriptor->getSerialId();
        $job->secondarySerialId = $jobDescriptor->getSecondarySerialId();
        $job->isMonitored = $jobDescriptor->isMonitored();

        return $job;
    }

    /**
     * @return array
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return string
     */
    public function getClass() {
        return $this->class;
    }

    /**
     * @return int
     */
    public function getFailCount() {
        return $this->failCount;
    }

    /**
     * @return string
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * @return string
     */
    public function getSecondarySerialId() {
        return $this->secondarySerialId;
    }

    /**
     * @return string
     */
    public function getSerialId() {
        return $this->serialId;
    }

    /**
     * @return string
     */
    public function getUniqueId() {
        return $this->uniqueId;
    }

    /**
     * @return Job
     */
    public function incFailCount() {
        $this->failCount++;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isMonitored() {
        return $this->isMonitored;
    }

    /**
     * @return bool TRUE if job is serial; otherwise FALSE
     */
    public function isSerial() {
        return strlen(trim($this->getSerialId())) > 0;
    }

    /**
     * @param string $queue
     *
     * @return Job
     */
    public function setQueue($queue) {
        $this->queue = $queue;

        return $this;
    }

    public function toArray() {
        return array_filter([
                'class' => $this->class,
                'args' => $this->args,
                'queue' => $this->queue,
                'uniqueId' => $this->uniqueId,
                'serialId' => $this->serialId,
                'secondarySerialId' => $this->secondarySerialId,
                'isMonitored' => $this->isMonitored,
                'failCount' => $this->failCount,
        ]);
    }

    public function toString() {
        return json_encode($this->toArray());
    }
}