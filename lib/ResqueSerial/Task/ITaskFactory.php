<?php

namespace ResqueSerial\Task;

interface ITaskFactory {
    /**
     * @param $className
     * @param array $args
     * @param $queue
     *
     * @return ITask
     */
    public function create($className, $args, $queue);
}
