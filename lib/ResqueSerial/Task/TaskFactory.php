<?php

namespace ResqueSerial\Task;

use ResqueSerial\Exception\BaseException;

class TaskFactory implements ITaskFactory {

    /**
     * @param $className
     * @param array $args
     * @param $queue
     *
     * @return ITask
     * @throws \ResqueSerial\Exception\BaseException
     */
    public function create($className, $args, $queue) {
        if (!class_exists($className)) {
            throw new BaseException(
                    'Could not find job class ' . $className . '.'
            );
        }

        if (!method_exists($className, 'perform')) {
            throw new BaseException(
                    'Job class ' . $className . ' does not contain a perform method.'
            );
        }

        $instance = new $className;
        $instance->args = $args;
        $instance->queue = $queue;

        return $instance;
    }
}
