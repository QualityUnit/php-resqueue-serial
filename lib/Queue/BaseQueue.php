<?php


namespace Resque\Queue;


use Resque\Resque;

class BaseQueue implements IQueue {

    /** @var string */
    private $key;

    /**
     * @param string $key redis key to store this queue in
     */
    public function __construct($key) {
        $this->key = $key;
    }

    /**
     * @return string redis key of this queue
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * @return string|null payload
     * @throws \Resque\RedisError
     */
    public function pop() {
        return Resque::redis()->rPop($this->key) ?: null;
    }

    /**
     * @param int $timeout Timeout in seconds
     *
     * @return string|null payload
     * @throws \Resque\RedisError
     */
    public function popBlocking($timeout) {
        $payload = Resque::redis()->brPop($this->key, $timeout);
        if (!is_array($payload) || !isset($payload[1])) {
            return null;
        }

        return $payload[1];
    }

    /**
     * @param IQueue $destinationQueue
     *
     * @return string|null
     * @throws \Resque\RedisError
     */
    public function popInto(IQueue $destinationQueue) {
        return Resque::redis()->rPoplPush($this->key, $destinationQueue->getKey()) ?: null;
    }

    /**
     * @param IQueue $destinationQueue
     * @param int $timeout Timeout in seconds
     *
     * @return string|null
     * @throws \Resque\RedisError
     */
    public function popIntoBlocking(IQueue $destinationQueue, $timeout) {
        return Resque::redis()->brPoplPush($this->key, $destinationQueue->getKey(), $timeout) ?: null;
    }

    /**
     * @param string $payload
     *
     * @return string
     * @throws \Resque\RedisError
     */
    public function push($payload) {
        Resque::redis()->lPush($this->key, $payload);

        return $payload;
    }
}