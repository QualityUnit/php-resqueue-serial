<?php


namespace Resque\Api;

use Resque\Exception;
use Throwable;

class RescheduleException extends Exception {

    /** @var int */
    private $delay;
    /* @var null|JobDescriptor */
    private $jobDescriptor;

    /**
     * @param JobDescriptor|null $job
     * @param int $delay in seconds
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(JobDescriptor $job = null, $delay = 0, $message = "", $code = 0,
            Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->delay = $delay;
        $this->jobDescriptor = $job;
    }

    /**
     * @return int (in seconds)
     */
    public function getDelay() {
        return $this->delay;
    }

    /**
     * @return null|JobDescriptor
     */
    public function getJobDescriptor() {
        return $this->jobDescriptor;
    }

}