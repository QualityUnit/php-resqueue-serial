<?php

require_once '/home/dmolnar/work/qu/php-resqueue-serial/vendor/autoload.php';


//Resque::removeQueue('test_queue');

Resque::setBackend("localhost:6379");

function enqueue() {
    for ($i = 0; $i < 10; $i++) {
        Resque::enqueue('test_queue', \ResqueSerial\DummyJob::class, [
                "key1" => "value$i",
                "key2" => "value$i"
        ], true);
    }
}

enqueue();

use ResqueSerial\Job;
use ResqueSerial\SerialJobInterface;

class La_Job_IndexTicket extends Job implements SerialJobInterface {

    private $ticket;

    /**
     * @return bool
     */
    public function perform() {
        // TODO: Implement perform() method.
    }


    /**
     * @return mixed[]
     */
    function getArgs() {
        // TODO: Implement getArgs() method.
    }

    /**
     * @return string
     */
    function getSecondarySerialId() {
        // TODO: Implement getSecondarySerialId() method.
    }

    /**
     * @return string
     */
    function getSerialId() {
        // TODO: Implement getSerialId() method.
    }

    /**
     * @return string
     */
    function getClass() {
        return self::class;
    }
}

$serialJob = new La_Job_IndexTicket();
$serialJob->setTicket($ticket);

ResqueSerial::enqueue('serial_q_name', $serialJob);