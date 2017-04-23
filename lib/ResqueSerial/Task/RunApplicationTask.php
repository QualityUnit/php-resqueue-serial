<?php


namespace ResqueSerial\Task;


use ResqueSerial\Init\GlobalConfig;

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
        list($applicationPath, $jobArgs, $taskClass) = $this->initializeArgs();

        $this->includeApplication($applicationPath);

        $this->checkTaskClass($taskClass);

        $performerGetter = self::PERFORMER_GETTER;
        if ($this->job->getFails() > 0) {
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
     * @throws TaskCreationException
     */
    private function checkPerformer($performer) {
        if (!method_exists($performer, self::VALIDATION_METHOD)) {
            throw new TaskCreationException("Performer $performer does not contain a validation method.");
        }
        if (!method_exists($performer, self::PERFORM_METHOD)) {
            throw new TaskCreationException("Performer $performer does not contain a perform method.");
        }
    }

    /**
     * @param $taskClass
     *
     * @throws TaskCreationException
     */
    private function checkTaskClass($taskClass) {
        if (!class_exists($taskClass)) {
            throw new TaskCreationException("Could not find application task class $taskClass.");
        }
        if (!method_exists($taskClass, self::PERFORMER_GETTER)) {
            throw new TaskCreationException("Task class $taskClass does not contain a performer getter.");
        }
        if (!method_exists($taskClass, self::RETRY_PERFORMER_GETTER)) {
            throw new TaskCreationException("Task class $taskClass does not contain a retry performer getter.");
        }
    }

    /**
     * @param $applicationPath
     */
    private function includeApplication($applicationPath) {
        $fullPath = GlobalConfig::instance()->getTaskIncludePath() . ltrim($applicationPath, '/');
        include_once $fullPath;
    }

    /**
     * @return array
     * @throws TaskCreationException
     */
    private function initializeArgs() {
        $applicationPath = @$this->args['include_path'];
        $jobArgs = @$this->args['job_args'];
        $taskClass = @$this->args['job_class'];
        $environment = @$this->args['environment'];

        if (is_array($environment)) {
            foreach ($environment as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }

        if ($applicationPath === null || !is_array($jobArgs) || $taskClass === null) {
            throw new TaskCreationException("Job arguments incomplete.");
        }

        return array($applicationPath, $jobArgs, $taskClass);
    }
}