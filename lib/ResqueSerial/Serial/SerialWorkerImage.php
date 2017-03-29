<?php


namespace ResqueSerial\Serial;


use Resque;
use ResqueSerial\Key;
use ResqueSerial\WorkerImage;
use ResqueSerial\WorkerImageTrait;

class SerialWorkerImage {
    use WorkerImageTrait;

    /**
     * @return string[] worker ids
     */
    public static function all() {
        return Resque::redis()->smembers(Key::serialWorkers());
    }
    /**
     * @return $this
     */
    public function addToPool() {
        Resque::redis()->sadd(Key::serialWorkers(), $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function clearParent() {
        Resque::redis()->del(Key::serialWorkerParent($this->id));
        return $this;
    }

    /**
     * @return $this
     */
    public function clearStarted() {
        Resque::redis()->del(Key::serialWorkerStart($this->id));
        return $this;
    }

    /**
     * @return $this
     */
    public function clearState() {
        Resque::redis()->del(Key::serialWorker($this->id));
        return $this;
    }

    /**
     * @return bool
     */
    public function exists() {
        return (bool)Resque::redis()->sismember(Key::serialWorkers(), $this->id);
    }

    /**
     * @return bool|string
     */
    public function getParent() {
        return Resque::redis()->get(Key::serialWorkerParent($this->id));
    }

    /**
     * @return mixed
     */
    public function getState() {
        return Resque::redis()->get(Key::serialWorker($this->id));
    }

    /**
     * @return bool
     */
    public function isOrphaned() {
        $parent = $this->getParent();

        return $parent == '' || !WorkerImage::fromId($parent)->isAlive();
    }

    /**
     * @return $this
     */
    public function removeFromPool() {
        Resque::redis()->srem(Key::serialWorkers(), $this->id);
        return $this;
    }

    /**
     * @param string $parentId
     *
     * @return $this
     */
    public function setParent($parentId) {
        Resque::redis()->set(Key::serialWorkerParent($this->id), $parentId);
        return $this;
    }

    /**
     * @return $this
     */
    public function setStartedNow() {
        Resque::redis()->set(Key::serialWorkerStart($this->id), strftime('%a %b %d %H:%M:%S %Z %Y'));
        return $this;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setState($data) {
        Resque::redis()->set(Key::serialWorker($this->id), $data);
        return $this;
    }
}