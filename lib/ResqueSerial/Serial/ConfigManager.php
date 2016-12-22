<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;

class ConfigManager {

    /**
     * @var string
     */
    private $queueKey;

    /**
     * Config constructor.
     *
     * @param $serialQueue
     */
    public function __construct($serialQueue) {
        $this->queueKey = Key::serialConfig($serialQueue);
    }

    /**
     * @return Config
     */
    public function getCurrent() { // FIXME
        if ($this->current === null) {
            $this->init();
        }

        return $this->current;
    }

    /**
     * @return Config
     */
    public function getLatest() { // FIXME
        if ($this->next !== null) {
            return $this->next;
        }

        return $this->getCurrent();
    }

    /**
     * @return int
     */
    public function getQueueCount() {
        return $this->getCurrent()->getQueueCount();
    }

    public function hasChanges() {
        return \Resque::redis()->lLen($this->queueKey) > 1;
    }

    public function init() {
        $this->load();
        if ($this->current === null) {
            $this->current = new Config();
            $this->next = null;
        }
    }

    public function isEmpty() {
        return \Resque::redis()->lLen($this->queueKey) < 1;
    }

    public function removeCurrent() {
        \Resque::redis()->lPop($this->queueKey);
    }

    private function load() {
        $this->current = null;
        $this->next = null;

        $current = \Resque::redis()->lIndex($this->queueKey, 0);
        if ($current == null) {
            return;
        }
        $this->current = new Config(json_decode($current, true));

        $next = \Resque::redis()->lIndex($this->queueKey, 1);
        if ($next != null) {
            return;
        }
        $this->next = new Config(json_decode($next, true));
    }
}