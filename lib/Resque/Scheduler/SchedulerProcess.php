<?php

namespace Resque\Scheduler;

use Resque;
use Resque\Api\UniqueException;
use Resque\Config\GlobalConfig;
use Resque\Job\Job;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\SignalHandler;
use Resque\UniqueList;

class SchedulerProcess {

    /** @var int Interval to sleep for between checking schedules. */
    protected $interval = 1;
    /** @var bool */
    private $isShutDown = false;
    /** @var IScheduler[] */
    private $schedulers = [];

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

    public function __construct() {
        Process::setTitlePrefix('scheduler');

        $this->schedulers = [
            new DelayedScheduler(),
            new PlannedScheduler()
        ];
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
            foreach ($this->schedulers as $scheduler) {
                $scheduler->execute();
            }
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

}
