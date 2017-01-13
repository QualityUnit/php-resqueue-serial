<?php

require_once '/home/dmolnar/work/qu/php-resqueue-serial/vendor/autoload.php';


//Resque::removeQueue('test_queue');

Resque::setBackend("localhost:6379");

use ResqueSerial\Job;

class La_Job_IndexTicket extends Job implements Resque_Task {

    private $ticket;

    /**
     * @return bool
     */
    public function perform() {
        usleep(100000);
        return true;
    }


    /**
     * @return mixed[]
     */
    function getArgs() {
        return [];
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

$serialJob = new La_Job_IndexTicket();

//Resque::redis()->lPush(\ResqueSerial\Key::serialQueueConfig('example_queue~test_job_serial_id'), '{"queueCount":2}');

for ($i=0; $i<20; $i++) {
    ResqueSerial::enqueue('example_queue', $serialJob);
}

$proc = new \ResqueSerial\Init\Process();

$proc->recover();
$proc->wait();