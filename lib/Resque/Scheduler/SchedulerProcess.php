<?php

namespace Resque\Scheduler;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Process\AbstractProcess;
use Resque\Process\BaseProcessImage;
use Resque\StatsD;

class SchedulerProcess extends AbstractProcess {

    /** @var int Interval to sleep for between checking schedules. */
    const SCHEDULE_INTERVAL = 1;
    /** @var IScheduler[] */
    private $schedulers = [];

    public function __construct() {
        parent::__construct('scheduler', BaseProcessImage::create());

        $this->schedulers = [
            new DelayedScheduler(),
            new PlannedScheduler()
        ];
    }

    public function deinit() {
        Resque::redis()->sRem(Key::localSchedulerProcesses(), $this->getImage()->getId());
    }

    public function doWork() {
        foreach ($this->schedulers as $scheduler) {
            $scheduler->execute();
        }

        sleep(self::SCHEDULE_INTERVAL);
    }

    public function init() {
        Resque::redis()->sAdd(Key::localSchedulerProcesses(), $this->getImage()->getId());
    }

    public function load() {
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
    }
}
