<?php


namespace Resque\Protocol;


class Job {

    /** @var string */
    protected $class = null;
    /** @var string */
    protected $sourceId = null;
    /** @var string */
    protected $name = null;
    /** @var array */
    protected $args = [];
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
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $array) {
        if (!isset($array['class'], $array['sourceId'], $array['name'])) {
            throw new \InvalidArgumentException('Mandatory Job parameters missing');
        }

        $job = new Job();
        $job->class = $array['class'] ?? $job->class;
        $job->sourceId = $array['sourceId'] ?? $job->sourceId;
        $job->name = $array['name'] ?? $job->name;

        $job->args = $array['args'] ?? $job->args;
        $job->isMonitored = $array['isMonitored'] ?? $job->isMonitored;
        $job->includePath = $array['includePath'] ?? $job->includePath;
        $job->environment = $array['environment'] ?? $job->environment;
        $job->failCount = $array['failCount'] ?? $job->failCount;
        $uidValid = isset($array['unique']) && \is_array($array['unique']);
        $job->uid = JobUid::fromArray($uidValid ? $array['unique'] : []);

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
    public function getName() {
        return $this->name;
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

    public function toArray() {
        return array_filter([
            'class' => $this->class,
            'sourceId' => $this->sourceId,
            'name' => $this->name,
            'args' => $this->args,
            'unique' => $this->uid === null ? null : $this->uid->toArray(),
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