<?php


namespace Resque\Worker;


use Resque;
use Resque\Key;

class WorkerImage extends WorkerImageBase {

    /**
     * @return string[] worker ids
     */
    public static function all() {
        return Resque::redis()->smembers(Key::workers());
    }

    /**
     * @return $this
     */
    public function addToPool() {
        Resque::redis()->sadd(Key::workers(), $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function clearStarted() {
        Resque::redis()->del(Key::workerStart($this->id));
        return $this;
    }

    /**
     * @return $this
     */
    public function clearState() {
        Resque::redis()->del(Key::worker($this->id));
        return $this;
    }

    /**
     * @return bool
     */
    public function exists() {
        return (bool)Resque::redis()->sismember(Key::workers(), $this->id);
    }

    /**
     * @return $this
     */
    public function removeFromPool() {
        Resque::redis()->srem(Key::workers(), $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function setStartedNow() {
        Resque::redis()->set(Key::workerStart($this->id), strftime('%a %b %d %H:%M:%S %Z %Y'));
        return $this;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function updateState($data) {
        Resque::redis()->set(Key::worker($this->id), $data);
        return $this;
    }
}