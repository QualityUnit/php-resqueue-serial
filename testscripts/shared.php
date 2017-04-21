<?php

use ResqueSerial\Job;

class La_Job_IndexTicket extends Job implements Resque_Task {

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

class __TestJob implements Resque_Task {

    private $arg;

    /**
     * @return bool
     */
    public function perform() {
        sleep(@$this->args['arg']);
        return true;
    }
}