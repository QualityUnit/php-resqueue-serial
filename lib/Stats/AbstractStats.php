<?php

namespace Resque\Stats;

use Resque\Config\GlobalConfig;
use Resque\StatsD;

abstract class AbstractStats {

    /** @var string */
    private $key;

    /**
     * @param string $key
     */
    public function __construct($key) {
        $this->key = $key;
    }

    protected function inc($stat, $value) {
        StatsD::increment($this->getKey($stat), $value);
    }

    protected function set($stat, $value) {
        StatsD::set($this->getKey($stat), $value);
    }

    private function getKey($stat) {
        return GlobalConfig::getInstance()->getNodeId() . ".{$this->key}.$stat";
    }
}