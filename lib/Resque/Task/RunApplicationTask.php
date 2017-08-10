<?php


namespace Resque\Task;


use Resque\Api\ApplicationTask;
use Resque\Config\GlobalConfig;

class RunApplicationTask implements ITask {

    public static function className() {
        return get_class();
    }

    public function perform() {
        list($version, $applicationPath, $jobArgs, $taskClass) = $this->initializeArgs($this->job->getArgs());

        $this->includeApplication($version, $applicationPath);

        ApplicationTask::checkTaskClass($taskClass);

        $performerGetter = ApplicationTask::PERFORMER_GETTER;
        if ($this->job->getFailCount() > 0) {
            $performerGetter = ApplicationTask::RETRY_PERFORMER_GETTER;
        }
        $performer = $taskClass::{$performerGetter}($jobArgs);

        ApplicationTask::checkPerformer($performer);

        $performer->{ApplicationTask::PERFORM_METHOD}();
    }

    /**
     * @param string $version
     * @param string $applicationPath
     */
    private function includeApplication($version, $applicationPath) {
        $fullPath = str_replace('{version}', $version, GlobalConfig::getInstance()->getTaskIncludePath())
                . ltrim($applicationPath, '/');
        include_once $fullPath;
    }

    /**
     * @param mixed[] $args
     *
     * @return mixed[]
     * @throws \Exception
     */
    private function initializeArgs(array $args) {
        $version = isset($args['version']) ? $args['version'] : null;
        $applicationPath = isset($args['include_path']) ? $args['include_path'] : null;
        $jobArgs = isset($args['job_args']) ? $args['job_args'] : null;
        $taskClass = isset($args['job_class']) ? $args['job_class'] : null;
        $environment = isset($args['environment']) ? $args['environment'] : null;

        if (is_array($environment)) {
            foreach ($environment as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }

        if ($version === null || $applicationPath === null || !is_array($jobArgs) || $taskClass === null) {
            throw new \Exception("Job arguments incomplete.");
        }

        return [$version, $applicationPath, $jobArgs, $taskClass];
    }
}