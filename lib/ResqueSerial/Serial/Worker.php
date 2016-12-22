<?php


namespace ResqueSerial\Serial;


use ResqueSerial\SerialTask;

class Worker {

    const RECOMPUTE_CONFIG_EVENT = "recomputeConfig";

    /**
     * @var IWorker
     */
    private $state;
    /**
     * @var Queue
     */
    private $queue;
    /**
     * @var SerialTask
     */
    private $task;

    /**
     * SerialWorker constructor.
     *
     * @param SerialTask $task
     */
    public function __construct(SerialTask $task) {
        $this->queue = new Queue($this->task->getQueue());
        $this->task = $task;
    }

    public function recompute() {
        // TODO
    }

    public function work() {

        $this->state = $this->initStateFromConfig();

        $recompute = [$this, 'recompute'];

        \Resque_Event::listen(self::RECOMPUTE_CONFIG_EVENT, $recompute);

        $this->recompute();
        while (true) {
            $this->state->work();
            $this->queue->config()->removeCurrent();

            if ($this->isToBeTerminated()) {
                break;
            }

            $this->state = $this->changeStateFromConfig();
        }
        //todo: deinit

        \Resque_Event::stopListening(self::RECOMPUTE_CONFIG_EVENT, $recompute);
    }

    private function changeStateFromConfig() {
        if ($this->queue->config()->getQueueCount() == 1) {
            return new Single($this->task->getQueue());
        } else {
            return new Multi($this->task->getQueue(), $this->queue->config());
        }
    }

    private function initStateFromConfig() {
        if ($this->queue->config()->isEmpty()) {
            $this->queue->config()->init();
        }

        return $this->changeStateFromConfig();
    }

    private function isToBeTerminated() {
        return $this->queue->config()->isEmpty();
    }
}