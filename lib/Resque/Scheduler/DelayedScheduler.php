<?php


namespace Resque\Scheduler;


use Resque;
use Resque\Job\Job;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\ResqueImpl;

class DelayedScheduler implements IScheduler {


    /**
     * Schedule all of the delayed jobs for a given timestamp.
     * Searches for all items for a given timestamp, pulls them off the list of
     * delayed jobs and pushes them across to Resque.
     *
     * @param int $timestamp Search for any items up to this timestamp to schedule.
     */
    public function enqueueItemsForTimestamp($timestamp) {
        while (($job = $this->nextJobForTimestamp($timestamp)) !== null) {
            Log::info("queueing {$job->getClass()} in {$job->getQueue()} [delayed]");

            ResqueImpl::getInstance()->jobEnqueue($job, false);
        }
    }

    /**
     * Handle delayed items for the next scheduled timestamp.
     * Searches for any items that are due to be scheduled in Resque
     * and adds them to the appropriate job queue in Resque.
     */
    public function execute() {
        while (($oldestJobTimestamp = $this->nextTimestamp()) !== false) {
            Process::setTitle('Processing Delayed Items');
            $this->enqueueItemsForTimestamp($oldestJobTimestamp);
        }
    }

    /**
     * If there are no jobs for a given key/timestamp, delete references to it.
     * Used internally to remove empty delayed: items in Redis when there are
     * no more jobs left to run at that timestamp.
     *
     * @param string $key Key to count number of items at.
     * @param int $timestamp Matching timestamp for $key.
     */
    private function cleanupTimestamp($key, $timestamp) {
        $redis = Resque::redis();

        if ($redis->llen($key) == 0) {
            $redis->del($key);
            $redis->zrem(Key::delayedQueueSchedule(), $timestamp);
        }
    }

    /**
     * Find the first timestamp in the delayed schedule before/including the timestamp.
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

        $items = Resque::redis()
            ->zrangebyscore(Key::delayedQueueSchedule(), '-inf', $at, ['limit' => [0, 1]]);
        if (!empty($items)) {
            return $items[0];
        }

        return false;
    }

    /**
     * Pop a job off the delayed queue for a given timestamp.
     *
     * @param int $timestamp Instance of DateTime or UNIX timestamp.
     *
     * @return null|Job Job at timestamp.
     */
    private function nextJobForTimestamp($timestamp) {
        $item = Resque::redis()->lpop(Key::delayed($timestamp));

        if (!$item) {
            // apparently broken timestamp
            Resque::redis()->zrem(Key::delayedQueueSchedule(), $timestamp);
            return null;
        }

        self::cleanupTimestamp(Key::delayed($timestamp), $timestamp);

        return Job::fromArray(json_decode($item, true));
    }

}