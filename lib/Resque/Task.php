<?php

/**
 * Interface Resque_JobInterface
 *
 * @property mixed[] args
 * @property string queue
 * @property Resque_Job job
 * @property Resque_Worker worker
 */
interface Resque_Task
{
	/**
	 * @return bool
	 */
	public function perform();
}
