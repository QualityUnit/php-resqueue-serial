<?php


namespace ResqueSerial;


use ResqueSerial\Task\ITask;

class SerialTaskFactory implements \Resque_Task_FactoryInterface {

    const SERIAL_CLASS = '-serial-task';

    /**
     * @var QueueLock
     */
    private $lock;

    /**
     * SerialTaskFactory constructor.
     *
     * @param QueueLock $lock
     */
    public function __construct(QueueLock $lock) {
        $this->lock = $lock;
    }

    /**
     * @param $className
     * @param array $args
     * @param $queue
     *
     * @return ITask
     * @throws \Exception
     */
    public function create($className, $args, $queue) {
        if ($className != self::SERIAL_CLASS) {
            throw new \Exception("Job class does not match expected serial class.");
        }
        $serialQueue = $args[SerialTask::ARG_SERIAL_QUEUE];

        return new SerialTask($serialQueue, $queue, $this->lock);
    }
}