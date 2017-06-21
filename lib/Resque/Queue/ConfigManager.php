<?php


namespace Resque\Queue;



use Resque;
use Resque\Key;

class ConfigManager {

    /** @var string */
    private $queueKey;

    /**
     * @param $serialQueue
     */
    public function __construct($serialQueue) {
        $this->queueKey = Key::serialQueueConfig($serialQueue);
    }

    /**
     * @return QueueConfig
     */
    public function getCurrent() {
        $current = Resque::redis()->lIndex($this->queueKey, 0);
        if ($current == null) {
            return $this->init();
        }
        return new QueueConfig(json_decode($current, true));
    }

    /**
     * @return QueueConfig
     */
    public function getLatest() {
        if(Resque::redis()->llen($this->queueKey) < 2) {
            return $this->getCurrent();
        }

        // TODO: crash on >2 ?
        $latest = Resque::redis()->lindex($this->queueKey, 1);
        if ($latest == null) {
                return null;
            }
        return new QueueConfig(json_decode($latest, true));
    }

    /**
     * @return int
     */
    public function getQueueCount() {
        return $this->getCurrent()->getQueueCount();
    }

    public function hasChanges() {
        return Resque::redis()->llen($this->queueKey) > 1;
    }

    public function isEmpty() {
        return Resque::redis()->llen($this->queueKey) < 1;
    }

    public function removeCurrent() {
        Resque::redis()->lpop($this->queueKey);
    }

    /**
     * @return QueueConfig
     */
    private function init() {
        $data = [
                'queueCount' => 1
        ];
        Resque::redis()->lpush($this->queueKey, json_encode($data));
        return new QueueConfig($data);
    }
}