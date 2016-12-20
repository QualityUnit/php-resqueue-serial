<?php


namespace ResqueSerial;


use ResqueSerial\Serial\Worker;

class SerialTask implements \Resque_Task {
    const ARG_SERIAL_QUEUE = "serialQueue";

    /**
     * @var Lock
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
    public function __construct($serialQueue, $queue, Lock $lock) {
        $this->serialQueue = $serialQueue;
        $this->queue = $queue;
        $this->lock = $lock;
    }

    /**
     * @return string
     */
    public function getQueue() {
        return $this->serialQueue;
    }

    public function perform() {
        // TODO pridat worker do redisu
        // $this->worker

        $worker = new Worker($this);
        $worker->work();

        // odobrat
    }
}