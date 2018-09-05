<?php

namespace Resque\Protocol;

class UniqueState {

    /** @var string */
    public $stateName;
    /** @var int */
    public $startTime;

    /**
     * @param string $stateName
     * @param float $startTime
     */
    public function __construct($stateName, $startTime = null) {
        $this->stateName = $stateName;
        $this->startTime = $startTime ?? time();
    }

    public static function fromString(string $stateString) {
        return new self(...explode('-', $stateString));
    }

    public function toString() {
        return "{$this->stateName}-{$this->startTime}";
    }
}