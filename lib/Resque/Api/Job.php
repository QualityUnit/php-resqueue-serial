<?php


namespace Resque\Api;


class Job {

    /** @var string */
    protected $class = null;
    /** @var array */
    protected $args = [];
    /** @var string */
    protected $queue = null;
    /** @var string */
    protected $sourceId = null;
    /** @var JobUid|null */
    protected $uid = null;
    /** @var boolean */
    protected $isMonitored = false;
    /** @var string */
    protected $includePath = null;
    /** @var string[] */
    protected $environment = null;
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
        $job->sourceId = isset($array['sourceId']) ? $array['sourceId'] : $job->sourceId;
        $job->isMonitored = isset($array['isMonitored']) ? $array['isMonitored'] : $job->isMonitored;
        $job->includePath = isset($array['includePath']) ? $array['includePath'] : $job->includePath;
        $job->environment = isset($array['environment']) ? $array['environment'] : $job->environment;
        $job->failCount = isset($array['failCount']) ? $array['failCount'] : $job->failCount;
        $uidValid = isset($array['unique']) && is_array($array['unique']);
        $job->uid = JobUid::fromArray($uidValid ? $array['unique'] : []);

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
        $job->sourceId = $jobDescriptor->getSourceId();
        $job->uid = $jobDescriptor->getUid();
        $job->isMonitored = $jobDescriptor->isMonitored();
        $job->includePath = $jobDescriptor->getIncludePath();
        $job->environment = $jobDescriptor->getEnvironment();

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
     * @return string[]
     */
    public function getEnvironment() {
        return $this->environment;
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
    public function getIncludePath() {
        return $this->includePath;
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
    public function getSourceId() {
        return $this->sourceId;
    }

    /**
     * @return null|JobUid
     */
    public function getUid() {
        return $this->uid;
    }

    /**
     * @return string
     */
    public function getUniqueId() {
        return $this->uid != null ? $this->uid->getId() : null;
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
                'sourceId' => $this->sourceId,
                'unique' => $this->uid == null ? null : $this->uid->toArray(),
                'isMonitored' => $this->isMonitored,
                'includePath' => $this->includePath,
                'environment' => $this->environment,
                'failCount' => $this->failCount,
        ]);
    }

    public function toString() {
        return json_encode($this->toArray());
    }
}