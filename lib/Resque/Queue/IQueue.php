<?php


namespace Resque\Queue;


interface IQueue {

    public function getKey();

    /**
     * @return mixed|null payload
     */
    public function pop();

    /**
     * @param int $timeout Timeout in seconds
     *
     * @return mixed|null payload
     */
    public function popBlocking($timeout);

    /**
     * @param IQueue $destinationQueue
     *
     * @return null|mixed
     */
    public function popInto(IQueue $destinationQueue);

    /**
     * @param IQueue $destinationQueue
     * @param int $timeout Timeout in seconds
     *
     * @return mixed|null
     */
    public function popIntoBlocking(IQueue $destinationQueue, $timeout);

    /**
     * @param mixed $payload
     *
     * @return mixed
     */
    public function push($payload);
}