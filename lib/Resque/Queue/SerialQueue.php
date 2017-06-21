<?php


namespace Resque\Queue;


use Resque;
use Resque\Job\IJobSource;
use Resque\Job\Job;
use Resque\Job\QueuedJob;
use Resque\Job\SerialJobLink;
use Resque\Key;
use Resque\ResqueImpl;

class SerialQueue implements IJobSource {

    private $name;

    public function __construct($name) {
        $this->name = $name;
    }

    /**
     * @param Job $job\
     * @return QueuedJob
     */
    public static function push(Job $job) {
        $mainQueue = $job->getQueue();
        $serialQueueImage = SerialQueueImage::create($mainQueue, $job->getSerialId());

        // push serial job to proper serial sub-queue
        $serialQueue = $serialQueueImage->generateSubqueueName($job->getSecondarySerialId());
        $serialJob = Job::fromArray($job->toArray())->setQueue($serialQueue);
        $queuedJob = new QueuedJob($serialJob, ResqueImpl::getInstance()->generateJobId());
        Resque::redis()->rpush(Key::serialQueue($serialQueue), json_encode($queuedJob->toArray()));

        // push serial queue link to main queue if it's not already being worked on
        if (!$serialQueueImage->lockExists()) {
            $serialLink = SerialJobLink::create($serialQueueImage->getQueue());
            $queuedLink = new QueuedJob($serialLink, $queuedJob->getId());
            Resque::redis()->rpush(Key::queue($mainQueue), json_encode($queuedLink->toArray()));
        }

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    function popBlocking($timeout) {
        $payload = Resque::redis()->blpop(Key::serialQueue($this->name), $timeout);
        if(!is_array($payload) || !isset($payload[1])) {
            return null;
        }

        $data = json_decode($payload[1], true);
        if (!is_array($data)) {
            return null;
        }

        $queuedJob = QueuedJob::fromArray($data);

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    function popNonBlocking() {
        $data = json_decode(Resque::redis()->lpop(Key::serialQueue($this->name)), true);
        if (!is_array($data)) {
            return null;
        }

        $queuedJob = QueuedJob::fromArray($data);

        return $queuedJob;
    }

    /**
     * @inheritdoc
     */
    public function toString() {
        return $this->name;
    }
}