<?php


namespace ResqueSerial;

use Resque;

class Queue {

    /** @var string[] */
    private $queues;

    /**
     * Resque_Queue constructor.
     *
     * @param string[] $queues
     */
    public function __construct($queues) {
        $this->queues = $queues;
    }

    /**
     * Find the next available job from the specified queues using blocking list pop
     * and return an instance of Resque Job for it.
     *
     * @param int $timeout
     *
     * @return false|object Null when there aren't any waiting jobs, instance of Resque Job when a job was found.
     */
    public function blockingPop($timeout = null) {
        $item = Resque::blpop($this->queues, $timeout);

        if (!is_array($item)) {
            return false;
        }

        return new ResqueJob($item['queue'], $item['payload']);
    }

    public function count() {
        return count($this->queues);
    }

    /**
     * @return string[]
     */
    public function getQueues() {
        return $this->queues;
    }

    /**
     * Find the next available job from the specified queue and return an
     * instance of Resque Job for it.
     *
     * @return false|ResqueJob Null when there aren't any waiting jobs, instance of Resque Job when a job was found.
     */
    public function pop() {
        foreach ($this->queues as $queue) {
            $payload = Resque::pop($queue);
            if (!is_array($payload)) {
                continue;
            }

            return new ResqueJob($queue, $payload);
        }

        return false;
    }
}