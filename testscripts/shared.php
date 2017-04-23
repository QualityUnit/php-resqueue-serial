<?php

use ResqueSerial\Job;
use ResqueSerial\Task\ITask;

class La_Job_IndexTicket extends Job implements ITask {

    private $arg;

    /**
     * @return bool
     */
    public function perform() {
        usleep(4000000);
        file_put_contents('/tmp/serialjob.txt', var_export($this->args, true) . "\n", FILE_APPEND);
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

    private $arg;

    /**
     * @return bool
     */
    public function perform() {
        sleep(@$this->args['arg']);
        return true;
    }
}

class __FailJob implements ITask {

    private $arg;

    public function perform() {
        throw new Exception(@$this->args['arg']);
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
        \ResqueSerial\Log::local()->notice("Performing job.");
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