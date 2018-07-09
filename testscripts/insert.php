<?php

use Resqu\Client\JobDescriptor;

require_once '/home/dmolnar/work/qu/php-resqueue-serial/scripts/bootstrap.php';
require_once 'testjobs.php';


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

    public function getSourceId() {
        return 'test';
    }

    public function getName() {
        return $this->class;
    }
}

\Resqu\Client::enqueue(new Descriptor(__SleepJob::class, [
    'sleep' => 10,
    'message' => 'Test message'
]));
