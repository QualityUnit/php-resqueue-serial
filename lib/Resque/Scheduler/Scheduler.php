<?php

namespace Resque\Scheduler;

use Resque;
use Resque\Api\UniqueException;
use Resque\Config\GlobalConfig;
use Resque\Job\Job;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\ResqueImpl;
use Resque\SignalHandler;
use Resque\UniqueList;

class Scheduler {

    /** @var int Interval to sleep for between checking schedules. */
    protected $interval = 1;
    /** @var bool */
    private $isShutDown = false;

    public function __construct() {
        Process::setTitlePrefix('scheduler');
    }

    public static function getLocalPid() {
        return Resque::redis()->get(Key::localSchedulerPid());
    }

    public static function schedule($at, Job $job, $checkUnique = true) {
        if (!UniqueList::add($job->getUniqueId()) && $checkUnique) {
            throw new UniqueException($job->getUniqueId());
        }

        Resque::redis()->rpush(Key::delayed($at), json_encode($job->toArray()));
        Resque::redis()->zadd(Key::delayedQueueSchedule(), $at, $at);
    }

    /**
     * Schedule all of the delayed jobs for a given timestamp.
     * Searches for all items for a given timestamp, pulls them off the list of
     * delayed jobs and pushes them across to Resque.
     *
     * @param int $timestamp Search for any items up to this timestamp to schedule.
     */
    public function enqueueDelayedItemsForTimestamp($timestamp) {
        while (($job = $this->nextJobForTimestamp($timestamp)) !== null) {
            Log::info("queueing {$job->getClass()} in {$job->getQueue()} [delayed]");

            ResqueImpl::getInstance()->jobEnqueue($job, false);
        }
    }

    /**
     * Handle delayed items for the next scheduled timestamp.
     * Searches for any items that are due to be scheduled in Resque
     * and adds them to the appropriate job queue in Resque.
     *
     * @param int $timestamp Search for any items up to this timestamp to schedule.
     */
    public function handleDelayedItems($timestamp = null) {
        while (($oldestJobTimestamp = $this->nextDelayedTimestamp($timestamp)) !== false) {
            Process::setTitle('Processing Delayed Items');
            $this->enqueueDelayedItemsForTimestamp($oldestJobTimestamp);
        }
    }

    public function reload() {
        Log::notice("Reloading");
        GlobalConfig::reload();
        $this->initLogger();
        Log::notice("Reloaded");
    }

    public function shutDown() {
        $this->isShutDown = true;
        Log::info("Shutting down");
    }

    /**
     * The primary loop for a worker.
     * Every $interval (seconds), the scheduled queue will be checked for jobs
     * that should be pushed to Resque.
     *
     * @param int $interval How often to check schedules.
     */
    public function work($interval = null) {
        if ($interval !== null) {
            $this->interval = $interval;
        }

        Process::setTitle('Starting');
        $this->initialize();


        Process::setTitle('Working');
        while ($this->canRun()) {
            $this->handleDelayedItems();
            sleep($this->interval);
        }


        Process::setTitle('Shutting down');
        $this->deinitialize();
    }

    /**
     * @return bool
     */
    private function canRun() {
        SignalHandler::dispatch();

        return !$this->isShutDown;
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

    private function deinitialize() {
        Resque::redis()->del(Key::localSchedulerPid());
        SignalHandler::instance()->unregisterAll();
        Log::notice("Ended");
    }

    private function initLogger() {
        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix(getmypid() . "-scheduler"));
    }

    private function initialize() {
        $this->initLogger();
        $pid = getmypid();
        Resque::redis()->set(Key::localSchedulerPid(), $pid);

        SignalHandler::instance()
                ->unregisterAll()
                ->register(SIGTERM, [$this, 'shutDown'])
                ->register(SIGINT, [$this, 'shutDown'])
                ->register(SIGQUIT, [$this, 'shutDown'])
                ->register(SIGHUP, [$this, 'reload']);

        Log::notice("Started scheduler at $pid");
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
    private function nextDelayedTimestamp($at = null) {
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
            return null;
        }

        self::cleanupTimestamp(Key::delayed($timestamp), $timestamp);

        return Job::fromArray(json_decode($item, true));
    }
}
