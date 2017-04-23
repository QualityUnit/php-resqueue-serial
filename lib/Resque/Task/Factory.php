<?php

class Resque_Task_Factory implements Resque_Task_FactoryInterface
{

    /**
     * @param $className
     * @param array $args
     * @param $queue
     * @return Resque_Task
     * @throws \Resque_Exception
     */
    public function create($className, $args, $queue)
    {
        if (!class_exists($className)) {
            throw new Resque_Exception(
                'Could not find job class ' . $className . '.'
            );
        }

        if (!method_exists($className, 'perform')) {
            throw new Resque_Exception(
                'Job class ' . $className . ' does not contain a perform method.'
            );
        }

        $instance = new $className;
        $instance->args = $args;
        $instance->queue = $queue;
        return $instance;
    }
}
