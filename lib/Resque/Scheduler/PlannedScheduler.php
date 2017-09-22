<?php


namespace Resque\Scheduler;

use Resque;
use Resque\Job\Job;
use Resque\Job\PlannedJob;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\ResqueImpl;

class PlannedScheduler implements IScheduler {

    const ENQUEUE_SCRIPT = <<<LUA
redis.call('sadd', KEYS[1], ARGV[1])
redis.call('lrem', KEYS[2], ARGV[2])
redis.call('lpush', KEYS[3], ARGV[2])
LUA;


    public static function insertJob(\DateTime $nextRun, \DateInterval $recurrencePeriod, Job $job) {
        $id = null;
        do {
            $id = microtime(true);
            $plannedJob = new PlannedJob($id, $nextRun, $recurrencePeriod, $job);
            $plannedJob->moveAfter(time());
        } while (!Resque::redis()->setNx(Key::plan($id), $plannedJob));

        $nextRun = $plannedJob->getNextRunTimestamp();

        Resque::redis()->zadd(Key::planSchedule(), $nextRun, $nextRun);
        Resque::redis()->rpush(Key::planTimestamp($nextRun), json_encode($plannedJob->toArray()));

        return $id;
    }

    public static function removeJob($id) {
        $redis = Resque::redis();
        $plannedJob = self::getPlannedJob($id);
        $redis->del(Key::plan($id));

        $timestamp = $plannedJob->getNextRunTimestamp();
        $timestampKey = Key::planTimestamp($timestamp);

        $redis->lRem($timestampKey, 1, $id);
        if ($redis->llen($timestampKey) == 0) {
            $redis->del($timestampKey);
            $redis->zrem(Key::planSchedule(), $timestamp);
        }
    }

    /**
     * @param $planId
     * @return null|PlannedJob
     */
    private static function getPlannedJob($planId) {
        $data = Resque::redis()->get(Key::plan($planId));
        if (!$data) {
            return null;
        }

        $decoded = json_decode($data);
        if (!is_array($decoded)) {
            Log::critical("Unknown format for planned job '$id':\n $data");
            return null;
        }

        return PlannedJob::fromArray($decoded);
    }

    /**
     * Schedule all of the planned jobs for a given timestamp.
     * Searches for all items for a given timestamp, pulls them off the list of
     * planned jobs and pushes them across to Resque.
     *
     * @param int $timestamp Search for any items up to this timestamp to schedule.
     */
    public function enqueueItemsForTimestamp($timestamp) {
        while (($plannedJob = $this->nextJobForTimestamp($timestamp)) !== null) {
            $job = $plannedJob->getJob();
            Log::info("queueing {$job->getClass()} in {$job->getQueue()} [planned]");

            $futurePlannedJob = $this->moveTimestamp($plannedJob, $timestamp);
            Resque::redis()->eval(
                    self::ENQUEUE_SCRIPT,
                    [
                            Key::planSchedule(),
                            Key::planTimestamp($timestamp),
                            Key::planTimestamp($futurePlannedJob->getNextRunTimestamp())
                    ],
                    [
                            $futurePlannedJob->getNextRunTimestamp(),
                            $plannedJob->getId()
                    ]
            );

            ResqueImpl::getInstance()->jobEnqueue($job, true);
        }
    }

    public function execute() {
        while (($oldestJobTimestamp = $this->nextTimestamp()) !== false) {
            Process::setTitle('Processing Planned Items');
            $this->enqueueItemsForTimestamp($oldestJobTimestamp);
        }
    }

    /**
     * If there are no jobs for a given key/timestamp, delete references to it.
     * Used internally to remove empty planned: items in Redis when there are
     * no more jobs left to run at that timestamp.
     *
     * @param int $timestamp Matching timestamp for $key.
     */
    private function cleanupTimestamp($timestamp) {
        $redis = Resque::redis();

        if ($redis->llen(Key::planTimestamp($timestamp)) == 0) {
            $redis->del(Key::planTimestamp($timestamp));
            $redis->zrem(Key::delayedQueueSchedule(), $timestamp);
        }
    }

    /**
     * @param PlannedJob $plannedJob
     * @param $timestamp
     * @return PlannedJob
     */
    private function moveTimestamp(PlannedJob $plannedJob, $timestamp) {
        $futurePlannedJob = $plannedJob->copy();
        $futurePlannedJob->moveAfter($timestamp);

        Resque::redis()->set(Key::plan($plannedJob->getId()), $plannedJob->toString());

        return $futurePlannedJob;
    }

    /**
     * Pop a job off the plan queue for a given timestamp.
     *
     * @param int $timestamp Instance of DateTime or UNIX timestamp.
     *
     * @return null|PlannedJob Job at timestamp.
     */
    private function nextJobForTimestamp($timestamp) {
        $planId = Resque::redis()->lpop(Key::planTimestamp($timestamp));

        $plannedJob = self::getPlannedJob($planId);
        if (!$plannedJob) {
            return null;
        }

        self::cleanupTimestamp($timestamp);

        return $plannedJob;
    }

    /**
     * Find the first timestamp in the plan schedule before/including the timestamp.
     * Will find and return the first timestamp upto and including the given
     * timestamp. This is the heart of the ResqueScheduler that will make sure
     * that any jobs scheduled for the past when the worker wasn't running are
     * also queued up.
     *
     * @param int $at UNIX timestamp. Defaults to now.
     *
     * @return int|false UNIX timestamp, or false if nothing to run.
     */
    private function nextTimestamp($at = null) {
        if ($at === null) {
            $at = time();
        }

        $items = Resque::redis()->zrangebyscore(Key::planSchedule(), '-inf', $at, ['limit' => [0, 1]]);
        if (!empty($items)) {
            return $items[0];
        }

        return false;
    }
}