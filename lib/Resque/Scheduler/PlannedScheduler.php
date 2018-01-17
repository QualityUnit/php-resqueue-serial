<?php


namespace Resque\Scheduler;

use Resque;
use Resque\Api\Job;
use Resque\Job\PlannedJob;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\Redis;
use Resque\ResqueImpl;

class PlannedScheduler implements IScheduler {

    const ENQUEUE_SCRIPT = /** @lang Lua */ <<<LUA
redis.call('zadd', KEYS[1], ARGV[1], ARGV[1])
redis.call('lrem', KEYS[2], 0, ARGV[2])
redis.call('rpush', KEYS[3], ARGV[2])
LUA;


    public static function insertJob(\DateTime $nextRun, \DateInterval $recurrenceInterval,
            Job $job) {
        $id = null;
        do {
            $id = microtime(true);
            $plannedJob = new PlannedJob($id, $nextRun, $recurrenceInterval, $job);
            $plannedJob->moveAfter(time());
        } while (!Resque::redis()->setNx(Key::plan($id), $plannedJob->toString()));

        $nextRun = $plannedJob->getNextRunTimestamp();

        Resque::redis()->zAdd(Key::planSchedule(), $nextRun, $nextRun);
        Resque::redis()->rPush(Key::planTimestamp($nextRun), $plannedJob->getId());

        return $id;
    }

    public static function removeJob($id) {
        $plannedJob = self::getPlannedJob($id);
        Resque::redis()->del(Key::plan($id));

        if ($plannedJob == null) {
            return false;
        }

        $timestamp = $plannedJob->getNextRunTimestamp();
        Resque::redis()->lRem(Key::planTimestamp($timestamp), 0, $id);

        self::cleanupTimestamp($timestamp);

        return true;
    }

    /**
     * If there are no jobs for a given key/timestamp, delete references to it.
     * Used internally to remove empty planned: items in Redis when there are
     * no more jobs left to run at that timestamp.
     *
     * @param int $timestamp Matching timestamp for $key.
     */
    private static function cleanupTimestamp($timestamp) {
        $redis = Resque::redis();

        if ($redis->lLen(Key::planTimestamp($timestamp)) == 0) {
            Log::info("[planner] Cleaning timestamp $timestamp");
            $redis->del(Key::planTimestamp($timestamp));
            $redis->zRem(Key::planSchedule(), $timestamp);
        }
    }

    /**
     * @param $planId
     *
     * @return null|PlannedJob
     */
    private static function getPlannedJob($planId) {
        $data = Resque::redis()->get(Key::plan($planId));
        if (!$data) {
            Log::info("[planner] No planned job '$planId'");

            return null;
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            Log::critical('[planner] Unknown format for planned job.', [
                'plan_id' => $planId,
                'payload' => $data
            ]);

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
            Log::info("[planner] queueing {$job->getClass()} in {$job->getQueue()}");

            $futurePlannedJob = $this->moveTimestamp($plannedJob, $timestamp);
            $prefix = Redis::getPrefix();
            Resque::redis()->eval(
                    self::ENQUEUE_SCRIPT,
                    [
                            $prefix . Key::planSchedule(),
                            $prefix . Key::planTimestamp($plannedJob->getNextRunTimestamp()),
                            $prefix . Key::planTimestamp($futurePlannedJob->getNextRunTimestamp())
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
            Log::info("[planner] Running plans for $oldestJobTimestamp");
            Process::setTitle('Processing Planned Items');
            $this->enqueueItemsForTimestamp($oldestJobTimestamp);
        }
    }

    /**
     * @param PlannedJob $plannedJob
     * @param $timestamp
     *
     * @return PlannedJob
     */
    private function moveTimestamp(PlannedJob $plannedJob, $timestamp) {
        $futurePlannedJob = $plannedJob->copy();
        $futurePlannedJob->moveAfter($timestamp);

        Resque::redis()->set(Key::plan($plannedJob->getId()), $plannedJob->toString());

        Log::info("[planner] Moved job {$plannedJob->getId()}"
                . " from {$plannedJob->getNextRunTimestamp()}"
                . " to {$futurePlannedJob->getNextRunTimestamp()}.");

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
        $planId = Resque::redis()->lPop(Key::planTimestamp($timestamp));

        self::cleanupTimestamp($timestamp);

        if ($planId == null) {
            return null;
        }

        Log::info("[planner] Looking for planned job $planId..");

        $plannedJob = self::getPlannedJob($planId);

        return $plannedJob ?: null;
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

        $items = Resque::redis()->zRangeByScore(Key::planSchedule(), '-inf', $at, [
                'limit' => [0, 1]
        ]);
        if (!empty($items)) {
            return $items[0];
        }

        return false;
    }
}