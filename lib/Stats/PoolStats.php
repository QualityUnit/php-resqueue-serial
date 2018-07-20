<?php

namespace Resque\Stats;

use Resque\SingletonTrait;

class PoolStats {

    use SingletonTrait;

    public function reportProcessed(string $poolName) {
        Stats::old()->increment("pools.$poolName.processed");
    }

    public function reportQueue(string $poolName, int $length) {
        Stats::old()->gauge("pools.$poolName.queue", $length);

        Stats::global()->gauge("pool.$poolName.queue", $length);
    }
}