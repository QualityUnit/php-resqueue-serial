<?php


namespace ResqueSerial;


use ResqueSerial\Serial\SerialWorker;

class SerialTask implements \Resque_Task {
    const ARG_SERIAL_QUEUE = "serialQueue";

    /**
     * @var QueueLock
     */
    private $lock;
    private $serialQueue;
    private $queue;

    /**
     * SerialTask constructor.
     *
     * @param $serialQueue
     * @param $queue
     * @param $lock
     */
    public function __construct($serialQueue, $queue, QueueLock $lock) {
        $this->serialQueue = $serialQueue;
        $this->queue = $queue;
        $this->lock = $lock;
    }

    public function perform() {
        $worker = new SerialWorker($this->serialQueue);
        $worker->work((string)$this->job->worker);
    }
}