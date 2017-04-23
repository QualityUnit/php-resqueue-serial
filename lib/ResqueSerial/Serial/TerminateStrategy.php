<?php


namespace ResqueSerial\Serial;


use ResqueSerial\ReserveStrategy\IReserveStrategy;
use ResqueSerial\DeprecatedWorker;

class TerminateStrategy implements IReserveStrategy {
    /**
     * @var DeprecatedWorker
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
     * @param DeprecatedWorker $worker
     * @param int $interval
     * @param bool $isParallel
     */
    public function __construct(DeprecatedWorker $worker, $interval = 1, $isParallel) {
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