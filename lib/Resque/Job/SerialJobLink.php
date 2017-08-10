<?php


namespace Resque\Job;


use Resque;
use Resque\Api\JobDescriptor;
use Resque\Exception;
use Resque\Key;

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

    /**
     * Whenever serial job is queued, existence of link pointing to its serial queue is checked
     * to make sure no needless links are added to the parent queue.
     *
     * @param string $serialQueue
     *
     * @return bool true if link is registered on the queue, false otherwise
     */
    public static function exists($serialQueue) {
        return Resque::redis()->get(Key::serialLink($serialQueue)) !== false;
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
     * Registers existence of a link for a specified queue. Called when enqueuing a link.
     *
     * @param string $serialQueue
     */
    public static function register($serialQueue) {
        Resque::redis()->set(Key::serialLink($serialQueue), 'true');
    }

    /**
     * Clears link registration for a specified queue. Called after acquiring a lock for the queue.
     *
     * @param string $serialQueue
     */
    public static function unregister($serialQueue) {
        Resque::redis()->del(Key::serialLink($serialQueue));
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