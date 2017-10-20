<?php

use Resque\Api\JobDescriptor;
use Resque\Task\ITask;

class __SerialJob extends JobDescriptor implements ITask {

    private $arg;

    /**
     * @return bool
     */
    public function perform() {
        usleep(400000);
        file_put_contents('/tmp/serialjob.txt', var_export($this->job->getArgs(), true) . "\n", FILE_APPEND);
        return true;
    }

    public function arg($arg) {
        $this->arg = $arg;
        return $this;
    }


    /**
     * @return mixed[]
     */
    function getArgs() {
        return ['arg' => $this->arg];
    }

    /**
     * @return string
     */
    function getSecondarySerialId() {
        return rand(0, 16);
    }

    /**
     * @return string
     */
    function getSerialId() {
        return 'test_job_serial_id';
    }

    /**
     * @return string
     */
    function getClass() {
        return self::class;
    }
}

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

}