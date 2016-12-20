<?php


namespace ResqueSerial\Serial;


use ResqueSerial\SerialTask;

class Worker  {

    const RECOMPUTE_CONFIG_EVENT = "recomputeConfig";

    /**
     * @var IWorker
     */
    private $state;
    private $configManager;

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
        $this->configManager = new ConfigManager($this->task->getQueue());
        $this->task = $task;
    }

    public function work() {

        $this->state = $this->initStateFromConfig();

        $recompute = [$this, 'recompute']; // TODO test

        \Resque_Event::listen(self::RECOMPUTE_CONFIG_EVENT, $recompute);

        while(true) {
            $this->state->work();
            $this->configManager->removeCurrent();

            if ($this->isToBeTerminated()) {
                break;
            }

            $this->state = $this->changeStateFromConfig();
        }
        //todo: deinit

        \Resque_Event::stopListening(self::RECOMPUTE_CONFIG_EVENT, $recompute);
    }

    private function changeStateFromConfig() {
        if($this->configManager->getQueueCount() == 1) {
            return new Single($this->task->getQueue());
        } else {
            return new Multi($this->task->getQueue());
        }
    }

    private function initStateFromConfig() {
        if($this->configManager->isEmpty()) {
            $this->configManager->init();
        }

        return $this->changeStateFromConfig();
    }

    private function isToBeTerminated() {
        return $this->configManager->isEmpty();
    }

}