<?php


namespace ResqueSerial\Serial;


use Resque;
use Resque_Event;
use Resque_Job;
use ResqueSerial\Key;

class Single extends \Resque_Worker implements IWorker {


    /**
     * @var bool
     */
    private $isParallel;

    public function __construct($queue, $isParallel = false) {
        parent::__construct([$queue]);
        $this->isParallel = $isParallel;
    }

    protected function initReserveStrategy($interval, $blocking) {
        $this->reserveStrategy = new TerminateStrategy($this, 1000, $this->isParallel);
    }


    public function work($interval = Resque::DEFAULT_INTERVAL, $blocking = false) {
        parent::work($interval, $blocking); // TODO: override init/deinit
    }

    protected function processJob(Resque_Job $job) {
        parent::processJob($job);
        Resque::redis()->incr(Key::serialCompletedCount($job->queue));
        Resque_Event::trigger(Worker::RECOMPUTE_CONFIG_EVENT, $this);
    }
}