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
        $this->queueKey = Key::serialQueueConfig($serialQueue);
    }

    /**
     * @return Config
     */
    public function getCurrent() {
        $current = \Resque::redis()->lIndex($this->queueKey, 0);
        if ($current == null) {
            return $this->init();
        }
        return new Config(json_decode($current, true));
    }

    /**
     * @return Config
     */
    public function getLatest() {
        if(\Resque::redis()->llen($this->queueKey) < 2) {
            return $this->getCurrent();
        }

        // TODO: crash on >2 ?
        $latest = \Resque::redis()->lindex($this->queueKey, 1);
        if ($latest == null) {
                return null;
            }
        return new Config(json_decode($latest, true));
    }

    /**
     * @return int
     */
    public function getQueueCount() {
        return $this->getCurrent()->getQueueCount();
    }

    public function hasChanges() {
        return \Resque::redis()->llen($this->queueKey) > 1;
    }

    public function isEmpty() {
        return \Resque::redis()->llen($this->queueKey) < 1;
    }

    public function removeCurrent() {
        \Resque::redis()->lpop($this->queueKey);
    }

    /**
     * @return Config
     */
    private function init() {
        $data = [
                'queueCount' => 1
        ];
        \Resque::redis()->lpush($this->queueKey, json_encode($data));
        return new Config($data);
    }
}