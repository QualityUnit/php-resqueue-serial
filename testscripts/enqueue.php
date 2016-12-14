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


class La_Job_IndexTicket extends ResqueSerial\Job {

}

$serialJob = new La_Job_IndexTicket($ticketId);

ResqueSerial::enqueue('serial_q_name', $serialJob);
