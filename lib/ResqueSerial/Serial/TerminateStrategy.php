<?php


namespace ResqueSerial\Serial;


use Resque_Worker;
use ResqueSerial\ReserveStrategy\IReserveStrategy;

class TerminateStrategy implements IReserveStrategy {
    /**
     * @var Resque_Worker
     */
    private $worker;
    /**
     * @var bool
     */
    private $hasWaitedOnce = false;
    /**
     * @var int
     */
    private $interval;
    /**
     * @var bool
     */
    private $isParallel;

    /**
     * Resque_Queue_WaitStrategy constructor.
     *
     * @param Resque_Worker $worker
     * @param int $interval
     * @param bool $isParallel
     */
    public function __construct(Resque_Worker $worker, $interval = 1, $isParallel) {
        $this->worker = $worker;
        $this->interval = $interval;
        $this->isParallel = $isParallel;
    }

    public function reserve() {
        $this->worker->updateProcLine('Waiting for ' . implode(',', $this->worker->queues()
                        ->getQueues()) . ' with interval ' . $this->interval);

        $job = $this->worker->reserve();

        if ($job) {
            $this->hasJob();

            return $job;
        }

        if ($this->shouldShutdown()) {
            $this->worker->shutdown();
        } else {
            usleep($this->interval * 1000000);
        }
        $this->noJob();

        return false;
    }

    private function configChanged() {  // TODO

    }

    private function hasJob() {
        $this->hasWaitedOnce = false;
    }

    private function noJob() {
        $this->hasWaitedOnce = true;
    }

    private function shouldShutdown() {
        if ($this->isParallel) {
            return $this->configChanged();
        }

        return $this->hasWaitedOnce;
    }
}