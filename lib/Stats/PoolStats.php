<?php

namespace Resque\Stats;

use Resque\SingletonTrait;

class PoolStats {

    use SingletonTrait;

    public function reportQueue(string $poolName, int $length) {
        Stats::global()->gauge("pool.$poolName.queue", $length);
    }
}