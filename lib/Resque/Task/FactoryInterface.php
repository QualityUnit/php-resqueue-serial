<?php

use ResqueSerial\Task\ITask;

interface Resque_Task_FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 *
	 * @return ITask
	 */
	public function create($className, $args, $queue);
}
