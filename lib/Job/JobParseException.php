<?php

namespace Resque\Job;

use Resque\Exception;

class JobParseException extends Exception {

    /** @var mixed */
    private $payload;

    public function __construct($payload) {
        parent::__construct('Mandatory Job parameters missing.');
        $this->payload = $payload;
    }

    /**
     * @return mixed
     */
    public function getPayload() {
        return $this->payload;
    }
}