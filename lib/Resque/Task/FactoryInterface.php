<?php

interface Resque_Task_FactoryInterface
{
	/**
	 * @param $className
	 * @param array $args
	 * @param $queue
	 * @return Resque_Task
	 */
	public function create($className, $args, $queue);
}
