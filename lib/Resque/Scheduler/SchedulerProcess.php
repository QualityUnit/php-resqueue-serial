<?php

namespace Resque\Scheduler;

use Resque;
use Resque\Process\AbstractProcess;
use Resque\Process\SchedulerImage;

class SchedulerProcess extends AbstractProcess {

    /** @var int Interval to sleep for between checking schedules. */
    const SCHEDULE_INTERVAL = 1;
    /** @var IScheduler[] */
    private $schedulers = [];

    public function __construct() {
        parent::__construct('scheduler', SchedulerImage::create());

        $this->schedulers = [
            new DelayedScheduler(),
            new PlannedScheduler()
        ];
    }

    public function doWork() {
        foreach ($this->schedulers as $scheduler) {
            $scheduler->execute();
        }

        sleep(self::SCHEDULE_INTERVAL);
    }

    protected function prepareWork() {
        // NOOP
    }
}
