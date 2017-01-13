<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;
use ResqueSerial\Lock;

class QueueImage {

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

    public function newLock() {
        return new Lock(Key::queueLock($this->queue));
    }
}