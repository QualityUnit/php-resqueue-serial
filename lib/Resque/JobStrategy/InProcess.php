<?php
/**
 * Runs the job in the same process as Resque_Worker
 *
 * @package		Resque/JobStrategy
 * @author		Chris Boulton <chris@bigcommerce.com>
 * @author		Erik Bernharsdon <bernhardsonerik@gmail.com>
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Resque_JobStrategy_InProcess implements Resque_JobStrategy_Interface
{
	/**
	 * @var Resque_Worker
	 */
	protected $worker;

	/**
	 * Set the Resque_Worker instance
	 *
	 * @param Resque_Worker $worker
	 */
	public function setWorker(Resque_Worker $worker)
	{
		$this->worker = $worker;
	}

	/**
	 * Run the job in the worker process
	 *
	 * @param Resque_Job $job
	 */
	public function perform(Resque_Job $job)
	{
		$status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
		$this->worker->updateProcLine($status);
		$this->worker->logger->info($status);
		$this->worker->perform($job);
	}

	/**
	 * Force an immediate shutdown of the worker, killing any child jobs
	 * currently working
	 */
	public function shutdown()
	{
		$this->worker->logger->info('No child to kill.');
	}
}
