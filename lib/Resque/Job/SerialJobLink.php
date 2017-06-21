<?php


namespace Resque\Job;


use Resque\Api\JobDescriptor;
use Resque\Exception;

class SerialJobLink extends JobDescriptor {

    const SERIAL_QUEUE_ARG = 'serial_queue';

    private static $JOB_CLASS = '-serial-link';
    private $args;

    /**
     * @param $serialQueue
     */
    private function __construct($serialQueue) {
        $this->args = [
            self::SERIAL_QUEUE_ARG => $serialQueue
        ];
    }

    /**
     * @param $serialQueue
     * @return Job
     */
    public static function create($serialQueue) {
        return Job::fromJobDescriptor(new self($serialQueue));
    }

    public static function getSerialQueue(Job $job) {
        if(!isset($job->getArgs()[self::SERIAL_QUEUE_ARG])) {
            throw new Exception('This is not a serial link job');
        }
        return $job->getArgs()[self::SERIAL_QUEUE_ARG];
    }

    public static function isSerialLink(Job $job) {
        return $job->getClass() == self::$JOB_CLASS;
    }

    /**
     * @return mixed
     */
    public function getArgs() {
        return $this->args;
    }

    /**
     * @return mixed
     */
    public function getClass() {
        return self::$JOB_CLASS;
    }
}