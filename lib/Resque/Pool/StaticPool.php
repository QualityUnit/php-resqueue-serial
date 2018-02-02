<?php

namespace Resque\Config;

use Resque;
use Resque\Key;

class StaticPool {

    /** @var string */
    private $poolName;
    /** @var int */
    private $workerCount;

    /**
     * @param string $poolName
     * @param int $workerCount
     */
    public function __construct($poolName, $workerCount) {
        $this->poolName = $poolName;
        $this->workerCount = (int)$workerCount;
    }

    /**
     * @param string $bufferKey
     *
     * @return string
     */
    public function assignJob($bufferKey) {
        return Resque::redis()->rPoplPush($bufferKey, Key::staticPoolQueue($this->poolName));
    }

    /**
     * @return int
     */
    public function getWorkerCount() {
        return $this->workerCount;
    }

}