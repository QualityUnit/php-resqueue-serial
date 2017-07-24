<?php


namespace Resque\Task;


use Resque\Config\GlobalConfig;

class RunApplicationTask implements ITask {

    // task class methods (static)
    const PERFORMER_GETTER = 'getPerformer';
    const RETRY_PERFORMER_GETTER = 'getRetryPerformer';
    // performer methods
    const PERFORM_METHOD = 'perform';
    const VALIDATION_METHOD = 'isValid';

    public static function className() {
        return get_class();
    }

    public function perform() {
        list($version, $applicationPath, $jobArgs, $taskClass) = $this->initializeArgs($this->job->getArgs());

        $this->includeApplication($version, $applicationPath);

        $this->checkTaskClass($taskClass);

        $performerGetter = self::PERFORMER_GETTER;
        if ($this->job->getFailCount() > 0) {
            $performerGetter = self::RETRY_PERFORMER_GETTER;
        }
        $performer = $taskClass::{$performerGetter}($jobArgs);

        $this->checkPerformer($performer);

        if ($performer->{self::VALIDATION_METHOD}()) {
            $performer->{self::PERFORM_METHOD}();
        }
    }

    /**
     * @param $performer
     *
     * @throws ApplicationTaskCreationException
     */
    private function checkPerformer($performer) {
        if (!method_exists($performer, self::VALIDATION_METHOD)) {
            throw new ApplicationTaskCreationException("Performer $performer does not contain a validation method.");
        }
        if (!method_exists($performer, self::PERFORM_METHOD)) {
            throw new ApplicationTaskCreationException("Performer $performer does not contain a perform method.");
        }
    }

    /**
     * @param $taskClass
     *
     * @throws ApplicationTaskCreationException
     */
    private function checkTaskClass($taskClass) {
        if (!class_exists($taskClass)) {
            throw new ApplicationTaskCreationException("Could not find application task class $taskClass.");
        }
        if (!method_exists($taskClass, self::PERFORMER_GETTER)) {
            throw new ApplicationTaskCreationException("Task class $taskClass does not contain a performer getter.");
        }
        if (!method_exists($taskClass, self::RETRY_PERFORMER_GETTER)) {
            throw new ApplicationTaskCreationException("Task class $taskClass does not contain a retry performer getter.");
        }
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
     * @throws ApplicationTaskCreationException
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
            throw new ApplicationTaskCreationException("Job arguments incomplete.");
        }

        return [$version, $applicationPath, $jobArgs, $taskClass];
    }
}