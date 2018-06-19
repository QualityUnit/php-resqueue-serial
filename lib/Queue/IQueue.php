<?php


namespace Resque\Queue;


interface IQueue {

    /**
     * @return string
     */
    public function getKey();

    /**
     * @return mixed|null payload
     */
    public function peek();

    /**
     * @return mixed|null payload
     * @throws \Resque\RedisError
     */
    public function pop();

    /**
     * @param int $timeout Timeout in seconds
     *
     * @return mixed|null payload
     * @throws \Resque\RedisError
     */
    public function popBlocking($timeout);

    /**
     * @param IQueue $destinationQueue
     *
     * @return null|mixed
     * @throws \Resque\RedisError
     */
    public function popInto(IQueue $destinationQueue);

    /**
     * @param IQueue $destinationQueue
     * @param int $timeout Timeout in seconds
     *
     * @return mixed|null
     * @throws \Resque\RedisError
     */
    public function popIntoBlocking(IQueue $destinationQueue, $timeout);

    /**
     * @param mixed $payload
     *
     * @return mixed
     * @throws \Resque\RedisError
     */
    public function push($payload);

    /**
     * @param mixed $payload
     *
     * @return void
     * @throws \Resque\RedisError
     */
    public function remove($payload);
}