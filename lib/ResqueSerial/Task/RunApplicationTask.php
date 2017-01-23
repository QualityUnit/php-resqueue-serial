<?php


namespace ResqueSerial\Task;


use ResqueSerial\Init\GlobalConfig;

class RunApplicationTask implements \Resque_Task {

    public static function className() {
        return get_class();
    }
    
    public function perform() {
        list($applicationPath, $jobArgs, $taskClass) = $this->initializeArgs();

        $this->includeApplication($applicationPath);

        $this->checkTaskClass($taskClass);

        $performer = call_user_func([$taskClass, 'getPerformer'], $jobArgs);

        $this->checkPerformer($performer, $taskClass);

        if ($performer->isValid()) {
            $performer->perform();
        }
    }

    /**
     * @param $performer
     * @param $taskClass
     *
     * @throws TaskCreationException
     */
    private function checkPerformer($performer, $taskClass) {
        if (!method_exists($performer, 'isValid')) {
            throw new TaskCreationException("Performer of $taskClass does not contain a isValid method.");
        }
        if (!method_exists($performer, 'perform')) {
            throw new TaskCreationException("Performer of $taskClass does not contain a perform method.");
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
        if (!method_exists($taskClass, 'getPerformer')) {
            throw new TaskCreationException("Task class $taskClass does not contain a getPerformer method.");
        }
}

    /**
     * @param $applicationPath
     */
    private function includeApplication($applicationPath) {
        $fullPath = GlobalConfig::instance()->getTaskIncludePath() . $applicationPath;
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

        if ($applicationPath === null || !is_array($jobArgs) || $taskClass === null) {
            throw new TaskCreationException("Job arguments incomplete.");
        }

        return array($applicationPath, $jobArgs, $taskClass);
    }
}