<?php

use Resque\Api\ITask;
use Resque\Api\JobDescriptor;

class __TestJob implements ITask {

    /**
     * @return bool
     */
    public function perform() {
        return true;
    }
}

class __SleepJob implements ITask {

    /**
     * @return bool
     */
    public function perform() {
        sleep(@$this->job->getArgs()['arg']);
        return true;
    }
}

class __FailJob implements ITask {

    public function perform() {
        throw new Exception(@$this->job->getArgs()['arg']);
    }
}

class __Fail__Perf {

    public function isValid() {
        return true;
    }
    
    public function perform() {
        throw new Exception();
    }
}

class __Pass__Perf {

    public function isValid() {
        return true;
    }

    public function perform() {
        \Resque\Log::notice("Performing job.");
    }
}

class __ApplicationTask {

    public static function getPerformer(array $args) {
        return new __Fail__Perf();
    }

    public static function getRetryPerformer(array $args) {
        return new __Pass__Perf();
    }
}

class Descriptor extends JobDescriptor {

    private $args;
    private $class;

    public function __construct($class, $args) {
        $this->args = $args;
        $this->class = $class;
    }

    public function getArgs() {
        return $this->args;
    }

    public function getClass() {
        return $this->class;
    }

    function getSourceId() {
        return 'test';
    }
}