<?php

namespace Resque\Pool;

use Resque\Job\StaticJobSource;
use Resque\Key;
use Resque\Queue\JobQueue;
use Resque\Resque;
use Resque\Worker\WorkerImage;

class StaticPool implements IPool {

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
     * @param string $poolName
     *
     * @return string
     * @throws \Resque\RedisError
     */
    public static function assignJob($bufferKey, $poolName) {
        return Resque::redis()->rPoplPush($bufferKey, Key::staticPoolQueue($poolName));
    }

    /**
     * @param WorkerImage $workerImage
     *
     * @return StaticJobSource
     */
    public function createJobSource(WorkerImage $workerImage) {
        $jobQueue = new JobQueue(Key::staticPoolQueue($this->poolName));
        $bufferQueue = new JobQueue(Key::workerBuffer($workerImage->getId()));

        return new StaticJobSource($jobQueue, $bufferQueue);
    }

    /**
     * @return string
     */
    public function getName() {
        return $this->poolName;
    }

    /**
     * @return int
     */
    public function getWorkerCount() {
        return $this->workerCount;
    }

}