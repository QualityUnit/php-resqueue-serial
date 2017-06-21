<?php


namespace Resque\Worker\Serial;


use Resque;
use Resque\Key;
use Resque\Worker\WorkerImage;
use Resque\Worker\WorkerImageBase;

class SerialWorkerImage extends WorkerImageBase {

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
     * @param string $state
     *
     * @return $this
     */
    public function updateState($state) {
        Resque::redis()->set(Key::serialWorker($this->id), $state);

        return $this;
    }

    public function addSubWorker($subWorkerId) {
        Resque::redis()->sadd(Key::serialWorkerRunners($this->id), $subWorkerId);
    }

    public function removeSubWorker($subWorkerId) {
        Resque::redis()->srem(Key::serialWorkerRunners($this->id), $subWorkerId);
    }

    public function getSubWorkers() {
        return Resque::redis()->smembers(Key::serialWorkerRunners($this->id));
    }
}