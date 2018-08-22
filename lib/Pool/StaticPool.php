<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Job\QueuedJob;
use Resque\Job\StaticJobSource;
use Resque\Key;
use Resque\Log;
use Resque\Protocol\UniqueList;
use Resque\Queue\JobQueue;
use Resque\Queue\Queue;
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
     * @param QueuedJob $queuedJob
     * @param Queue $buffer
     *
     * @return string|null
     * @throws \Resque\RedisError
     */
    public static function assignJob($queuedJob, $buffer) {
        $poolName = self::resolvePoolName($queuedJob);
        $uniqueId = $queuedJob->getJob()->getUniqueId();
        $poolQueue = new Queue(Key::staticPoolQueue($poolName));

        Log::debug("Assigning job to pool $poolName");

        if (!$uniqueId) {
            return $buffer->popInto($poolQueue);
        }

        $enqueued = UniqueList::add($uniqueId, $buffer->getKey(), $poolQueue->getKey());
        if ($enqueued !== false) {
            return $enqueued;
        }

        if ($queuedJob->getJob()->isDeferrable()) {
            $deferred = UniqueList::addDeferred($uniqueId, $buffer->getKey());
            if ($deferred !== false) {
                return $deferred;
            }

            return $buffer->popInto(new Queue(Key::unassigned()));
        }

        return $buffer->pop();
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return string
     */
    private static function resolvePoolName(QueuedJob $queuedJob) {
        return GlobalConfig::getInstance()->getStaticPoolMapping()->resolvePoolName(
            $queuedJob->getJob()->getSourceId(),
            $queuedJob->getJob()->getName()
        );
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