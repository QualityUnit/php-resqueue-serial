<?php


namespace Resque\Queue;


use Resque;
use Resque\Api\UniqueException;
use Resque\Job\IJobSource;
use Resque\Job\Job;
use Resque\Job\QueuedJob;
use Resque\Key;
use Resque\Log;
use Resque\ResqueImpl;
use Resque\Stats;
use Resque\UniqueList;

class Queue implements IJobSource {

    private $name;

    public function __construct($name) {
        $this->name = $name;
    }

    /**
     * @param Job $job
     * @param bool $checkUnique
     *
     * @return QueuedJob
     * @throws UniqueException
     */
    public static function push(Job $job, $checkUnique = true) {
        $queuedJob = new QueuedJob($job, ResqueImpl::getInstance()->generateJobId());

        if (!UniqueList::add($job->getUniqueId(), $queuedJob->getId()) && $checkUnique) {
            throw new UniqueException($job->getUniqueId());
        }

        // Push a job to the end of a specific queue. If the queue does not exist, then create it as well.
        Resque::redis()->sadd(Key::queues(), $job->getQueue());
        Resque::redis()->rpush(Key::queue($job->getQueue()), json_encode($queuedJob->toArray()));

        $queuedJob->reportQueued();

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    function popBlocking($timeout) {
        $payload = Resque::redis()->blpop(Key::queue($this->name), $timeout);
        if (!is_array($payload) || !isset($payload[1])) {
            return null;
        }

        $data = json_decode($payload[1], true);
        if (!is_array($data)) {
            Log::error('Payload data corrupted: ' . $payload[1]);
            return null;
        }

        Log::debug('Job retrieved from queue: ' . $payload[1]);

        $queuedJob = QueuedJob::fromArray($data);
        $this->writeStats($queuedJob);

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    function popNonBlocking() {
        $data = json_decode(Resque::redis()->lpop(Key::queue($this->name)), true);
        if (!is_array($data)) {
            return null;
        }

        $queuedJob = QueuedJob::fromArray($data);
        $this->writeStats($queuedJob);

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    public function toString() {
        return $this->name;
    }

    private function writeStats(QueuedJob $queuedJob) {
        $timeQueued = floor((microtime(true) - $queuedJob->getQueuedTime()) * 1000);
        Stats::incQueue($this->name, 'queue_time', $timeQueued);
        Stats::incQueue($this->name, 'dequeued');
    }
}