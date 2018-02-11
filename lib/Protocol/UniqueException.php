<?php

namespace Resque\Protocol;

use Resque\Exception;

class UniqueException extends Exception {

    public function __construct($uniqueId) {
        parent::__construct("A job with unique id '$uniqueId' is already in queue", 0, null);
    }

}