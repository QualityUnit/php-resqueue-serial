<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;

class Queue {

    /**
     * @var string
     */
    private $queue;
    /**
     * @var ConfigManager
     */
    private $configManager;

    /**
     * Queue constructor.
     *
     * @param string $queue
     */
    public function __construct($queue) {
        $this->queue = $queue;
        $this->configManager = new ConfigManager($queue);
    }

    public function config() {
        return $this->configManager;
    }

    public function getCheckpoints() {
        $points = \Resque::redis()->get(Key::serialCheckpoints($this->queue));
        if($points == null) {
            return [];
        }
        return json_decode($points, true);
    }

    public function getCompletedCount() {
        $count = \Resque::redis()->get(Key::serialCompletedCount($this->queue));
        if($count < 1) {
            return 0;
        }
        return $count;
    }

    public function setCheckpoints($checkpoints) {
        \Resque::redis()->set(Key::serialCheckpoints($this->queue), json_encode($checkpoints));
    }
}