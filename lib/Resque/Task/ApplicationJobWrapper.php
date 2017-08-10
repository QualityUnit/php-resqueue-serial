<?php


namespace Resque\Task;


use Resque\Api\ApplicationEnvironment;
use Resque\Api\JobDescriptor;

class ApplicationJobWrapper extends JobDescriptor {

    /** @var JobDescriptor */
    private $job;

    /** @var mixed[] */
    private $args = [];

    public function __construct(JobDescriptor $job, ApplicationEnvironment $environment) {
        $this->job = $job;

        $this->args['version'] = $environment->getVersion();
        $this->args['include_path'] = $environment->getIncludePath();
        $this->args['environment'] = $environment->getEnvironment();
        $this->args['job_class'] = $job->getClass();
        $this->args['job_args'] = $job->getArgs();
    }

    /**
     * @return mixed[]
     */
    function getArgs() {
        return $this->args;
    }

    /**
     * @return string
     */
    function getClass() {
        return RunApplicationTask::className();
    }

    /**
     * @return string|null
     */
    function getSecondarySerialId() {
        return $this->job->getSecondarySerialId();
    }

    /**
     * @return string|null
     */
    function getSerialId() {
        return $this->job->getSerialId();
    }

    public function getUniqueId() {
        return $this->job->getUniqueId();
    }

    public function isMonitored() {
        return $this->job->isMonitored();
    }
}