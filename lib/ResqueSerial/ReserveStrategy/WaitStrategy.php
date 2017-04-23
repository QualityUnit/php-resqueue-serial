<?php


namespace ResqueSerial\ReserveStrategy;

use Psr;
use ResqueSerial\DeprecatedWorker;

class WaitStrategy implements IReserveStrategy {

    /**
     * @var DeprecatedWorker
     */
    private $worker;
    /**
     * @var
     */
    private $interval;
    /**
     * @var
     */
    private $blocking;

    /**
     * Resque_Queue_WaitStrategy constructor.
     *
     * @param DeprecatedWorker $worker
     */
    public function __construct(DeprecatedWorker $worker, $interval, $blocking) {
        $this->worker = $worker;
        $this->interval = $interval;
        $this->blocking = $blocking;
    }

    public function reserve() {
        $job = false;

        if (!$this->worker->isPaused()) {
            if ($this->blocking === true) {
                $this->worker->logger->log(Psr\Log\LogLevel::INFO, 'Starting blocking with timeout of {interval}', array('interval' => $this->interval));
                $this->worker->updateProcLine('Waiting for ' . implode(',', $this->worker->queues()
                                ->getQueues()) . ' with blocking timeout ' . $this->interval);
                $job = $this->worker->reserveBlocking($this->interval);
            } else {
                $this->worker->updateProcLine('Waiting for ' . implode(',', $this->worker->queues()
                                ->getQueues()) . ' with interval ' . $this->interval);
                $job = $this->worker->reserve();
            }
        }

        if (!$job) {
            // For an interval of 0, break now - helps with unit testing etc
            if ($this->interval == 0) {
                return false;
            }

            if ($this->blocking === false) {
                // If no job was found, we sleep for $interval before continuing and checking again
                $this->worker->logger->log(Psr\Log\LogLevel::INFO, 'Sleeping for {interval}', array('interval' => $this->interval));
                if ($this->worker->isPaused()) {
                    $this->worker->updateProcLine('Paused');
                } else {
                    $this->worker->updateProcLine('Waiting for '
                            . implode(',', $this->worker->queues()->getQueues()));
                }

                usleep($this->interval * 1000000);
            }

            return false;
        }

        return $job;
    }
}