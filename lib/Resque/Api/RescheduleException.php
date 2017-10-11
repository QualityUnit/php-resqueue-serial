<?php


namespace Resque\Api;

use Resque\Exception;
use Throwable;

class RescheduleException extends Exception {

    /** @var int */
    private $delay;

    /**
     * RescheduleException constructor.
     * @param int $delay in seconds
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($delay = 0, $message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->delay = $delay;
    }

    /**
     * @return int (in seconds)
     */
    public function getDelay() {
        return $this->delay;
    }

}