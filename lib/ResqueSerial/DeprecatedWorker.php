<?php

namespace ResqueSerial;
declare(ticks=1);
use Exception;
use Psr;
use Resque;
use ResqueSerial\Job\DirtyExitException;
use ResqueSerial\Job\Status;
use ResqueSerial\JobStrategy\Fork;
use ResqueSerial\JobStrategy\IJobStrategy;
use ResqueSerial\JobStrategy\InProcess;
use ResqueSerial\ReserveStrategy\IReserveStrategy;
use ResqueSerial\ReserveStrategy\WaitStrategy;

/**
 * Resque worker that handles checking queues for jobs, fetching them
 * off the queues, running them and handling the result.
 *
 * @package        Resque/Worker
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class DeprecatedWorker {
    /**
     * @var Psr\Log\LoggerInterface Logging object that implements the PSR-3 LoggerInterface
     */
    public $logger;

    /**
     * @var array Array of all associated queues for this worker.
     */
    protected $queues = array();

    /**
     * @var string The hostname of this worker.
     */
    private $hostname;

    /**
     * @var boolean True if on the next iteration, the worker should shutdown.
     */
    private $shutdown = false;

    /**
     * @var boolean True if this worker is paused.
     */
    private $paused = false;

    /**
     * @var string String identifying this worker.
     */
    private $id;

    /**
     * @var ResqueJob Current job, if any, being processed by this worker.
     */
    protected $currentJob = null;

    /**
     * @var IJobStrategy
     */
    protected $jobStrategy = null;

    /**
     * @var IReserveStrategy
     */
    protected $reserveStrategy;

    /**
     * Return all workers known to Resque as instantiated instances.
     *
     * @return array
     */
    public static function all() {
        $workers = Resque::redis()->smembers('workers');
        if (!is_array($workers)) {
            $workers = array();
        }

        $instances = array();
        foreach ($workers as $workerId) {
            $instances[] = self::find($workerId);
        }

        return $instances;
    }

    /**
     * Given a worker ID, check if it is registered/valid.
     *
     * @param string $workerId ID of the worker.
     *
     * @return boolean True if the worker exists, false if not.
     */
    public static function exists($workerId) {
        return (bool)Resque::redis()->sismember('workers', $workerId);
    }

    /**
     * Given a worker ID, find it and return an instantiated worker class for it.
     *
     * @param string $workerId The ID of the worker.
     *
     * @return false|DeprecatedWorker Instance of the worker. False if the worker does not exist.
     */
    public static function find($workerId) {
        if (!self::exists($workerId) || false === strpos($workerId, ":")) {
            return false;
        }

        /** @noinspection PhpUnusedLocalVariableInspection */
        list($hostname, $pid, $queues) = explode(':', $workerId, 3);
        $queues = explode(',', $queues);
        $worker = new self($queues);
        $worker->setId($workerId);

        return $worker;
    }

    /**
     * @return bool
     */
    public function isPaused() {
        return $this->paused;
    }

    /**
     * Set the ID of this worker to a given ID string.
     *
     * @param string $workerId ID for the worker.
     */
    public function setId($workerId) {
        $this->id = $workerId;
    }

    /**
     * Instantiate a new worker, given a list of queues that it should be working
     * on. The list of queues should be supplied in the priority that they should
     * be checked for jobs (first come, first served)
     * Passing a single '*' allows the worker to work on all queues in alphabetical
     * order. You can easily add new queues dynamically and have them worked on using
     * this method.
     *
     * @param string|array $queues String with a single queue name, array with multiple.
     */
    public function __construct($queues) {
        if (!is_array($queues)) {
            $queues = array($queues);
        }

        $this->queues = $queues;
        if (function_exists('gethostname')) {
            $hostname = gethostname();
        } else {
            $hostname = php_uname('n');
        }
        $this->hostname = $hostname;
        $this->id = $this->hostname . ':' . getmypid() . ':' . implode(',', $this->queues);

        if (function_exists('pcntl_fork')) {
            $this->setJobStrategy(new Fork);
        } else {
            $this->setJobStrategy(new InProcess);
        }
    }

    /**
     * Get the JobStrategy used to seperate the job execution context from the worker
     *
     * @return IJobStrategy
     */
    public function getJobStrategy() {
        return $this->jobStrategy;
    }

    /**
     * Set the JobStrategy used to seperate the job execution context from the worker
     *
     * @param IJobStrategy $jobStrategy
     */
    public function setJobStrategy(IJobStrategy $jobStrategy) {
        $this->jobStrategy = $jobStrategy;
        $this->jobStrategy->setWorker($this);
    }

    /**
     * The primary loop for a worker which when called on an instance starts
     * the worker's life cycle.
     * Queues are checked every $interval (seconds) for new jobs.
     *
     * @param int $interval How often to check for new jobs across the queues.
     * @param bool $blocking
     */
    public function work($interval = Resque::DEFAULT_INTERVAL, $blocking = false) {
        $this->updateProcLine('Starting');
        $this->startup();

        $this->initReserveStrategy($interval, $blocking);

        while (true) {
            if ($this->shutdown) {
                break;
            }

            $job = $this->reserveStrategy->reserve();

            if (!$job) {
                continue;
            }

            $this->processJob($job);
        }

        $this->unregisterWorker();
    }

    /**
     * Process a single job.
     *
     * @param ResqueJob $job The job to be processed.
     */
    public function perform(ResqueJob $job) {
        try {
            EventBus::trigger('afterFork', $job);
            $job->perform();
        } catch (Exception $e) {
            $this->logger->log(Psr\Log\LogLevel::CRITICAL, '{job} has failed {stack}', array(
                    'job' => $job,
                    'stack' => $e
            ));
            $job->fail($e);

            return;
        }

        $job->updateStatus(Status::STATUS_COMPLETE);
        $this->logger->log(Psr\Log\LogLevel::NOTICE, '{job} has finished', array('job' => $job));
    }

    /**
     * @param  bool $blocking
     * @param  int $timeout
     *
     * @return ResqueJob|false Instance of Resque Job if a job is found, false if not.
     */
    public function reserveInternal($blocking, $timeout = null) {
        $queues = $this->queues();

        if ($blocking === true) {
            $job = $queues->blockingPop($timeout);
        } else {
            $this->logger->log(Psr\Log\LogLevel::INFO, 'Checking {queues} for jobs', array('queues' => implode(',', $queues->getQueues())));
            $job = $queues->pop();
        }
        if ($job) {
            $this->logger->log(Psr\Log\LogLevel::INFO, 'Found job on {queue}', array('queue' => $job->queue));

            return $job;
        }

        return false;
    }

    /**
     * @return ResqueJob|false Instance of Resque Job if a job is found, false if not.
     */
    public function reserve() {
        return $this->reserveInternal(false);
    }

    /**
     * @param  int $timeout
     *
     * @return ResqueJob|false Instance of Resque Job if a job is found, false if not.
     */
    public function reserveBlocking($timeout = null) {
        return $this->reserveInternal(true, $timeout);
    }

    /**
     * Return an array containing all of the queues that this worker should use
     * when searching for jobs.
     * If * is found in the list of queues, every queue will be searched in
     * alphabetic order. (@see $fetch)
     *
     * @param boolean $fetch If true, and the queue is set to *, will fetch
     * all queue names from redis.
     *
     * @return Queue wrapper around associated queues.
     */
    public function queues($fetch = true) {
        if (!in_array('*', $this->queues) || $fetch == false) {
            return new Queue($this->queues);
        }

        $queues = Resque::queues();
        sort($queues);

        return new Queue($queues);
    }

    /**
     * @param $interval
     * @param $blocking
     */
    protected function initReserveStrategy($interval, $blocking) {
        $this->reserveStrategy = new WaitStrategy($this, $interval, $blocking);
    }

    /**
     * @param ResqueJob $job
     */
    protected function processJob(ResqueJob $job) {
        $this->logger->log(Psr\Log\LogLevel::NOTICE, 'Starting work on {job}', array('job' => $job));
        EventBus::trigger('beforeFork', $job);
        $this->workingOn($job);

        $this->jobStrategy->perform($job);

        $this->doneWorking();
    }

    /**
     * Perform necessary actions to start a worker.
     */
    protected function startup() {
        $this->registerSigHandlers();
        $this->pruneDeadWorkers();
        EventBus::trigger('beforeFirstFork', $this);
        $this->registerWorker();
    }

    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title.
     */
    public function updateProcLine($status) {
        $processTitle = 'resque-' . Resque::VERSION . ': ' . $status;
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        } else if (function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    /**
     * Register signal handlers that a worker should respond to.
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers() {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
        pcntl_signal(SIGINT, array($this, 'shutDownNow'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR1, array($this, 'killChild'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
        $this->logger->log(Psr\Log\LogLevel::DEBUG, 'Registered signals');
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing() {
        $this->logger->log(Psr\Log\LogLevel::NOTICE, 'USR2 received; pausing job processing');
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing() {
        $this->logger->log(Psr\Log\LogLevel::NOTICE, 'CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown() {
        $this->shutdown = true;
        $this->logger->log(Psr\Log\LogLevel::NOTICE, 'Shutting down');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow() {
        $this->shutdown();
        $this->killChild();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChild() {
        $this->jobStrategy->shutdown();
    }

    /**
     * Look for any workers which should be running on this server and if
     * they're not, remove them from Redis.
     * This is a form of garbage collection to handle cases where the
     * server may have been killed and the Resque workers did not die gracefully
     * and therefore leave state information in Redis.
     */
    public function pruneDeadWorkers() {
        $workerPids = $this->workerPids();
        $workers = self::all();
        foreach ($workers as $worker) {
            if (is_object($worker)) {
                /** @noinspection PhpUnusedLocalVariableInspection */
                list($host, $pid, $queues) = explode(':', (string)$worker, 3);
                if ($host != $this->hostname || in_array($pid, $workerPids) || $pid == getmypid()) {
                    continue;
                }
                $this->logger->log(Psr\Log\LogLevel::INFO, 'Pruning dead worker: {worker}', array('worker' => (string)$worker));
                $worker->unregisterWorker();
            }
        }
    }

    /**
     * Return an array of process IDs for all of the Resque workers currently
     * running on this machine.
     *
     * @return array Array of Resque worker process IDs.
     */
    public function workerPids() {
        $pids = array();
        exec('ps -A -o pid,command | grep [r]esque', $cmdOutput);
        foreach ($cmdOutput as $line) {
            list($pids[],) = explode(' ', trim($line), 2);
        }

        return $pids;
    }

    /**
     * Register this worker in Redis.
     */
    public function registerWorker() {
        Resque::redis()->sadd('workers', (string)$this);
        Resque::redis()->set('worker:' . (string)$this
                . ':started', strftime('%a %b %d %H:%M:%S %Z %Y'));
    }

    /**
     * Unregister this worker in Redis. (shutdown etc)
     */
    public function unregisterWorker() {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new DirtyExitException);
        }

        $id = (string)$this;
        Resque::redis()->srem('workers', $id);
        Resque::redis()->del('worker:' . $id);
        Resque::redis()->del('worker:' . $id . ':started');
        Stats::clear('processed:' . $id);
        Stats::clear('failed:' . $id);
    }

    /**
     * Tell Redis which job we're currently working on.
     *
     * @param ResqueJob $job Resque Job instance containing the job we're working on.
     */
    public function workingOn(ResqueJob $job) {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(Status::STATUS_RUNNING);
        $data = json_encode(array(
                'queue' => $job->queue,
                'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                'payload' => $job->payload
        ));
        Resque::redis()->set('worker:' . $job->worker, $data);
    }

    /**
     * Notify Redis that we've finished working on a job, clearing the working
     * state and incrementing the job stats.
     */
    public function doneWorking() {
        $this->currentJob = null;
        Stats::incr('processed');
        Stats::incr('processed:' . (string)$this);
        Resque::redis()->del('worker:' . (string)$this);
    }

    /**
     * Generate a string representation of this worker.
     *
     * @return string String identifier for this worker instance.
     */
    public function __toString() {
        return $this->id;
    }

    /**
     * Return an object describing the job this worker is currently working on.
     *
     * @return array Object with details of current job.
     */
    public function job() {
        $job = Resque::redis()->get('worker:' . $this);
        if (!$job) {
            return array();
        } else {
            return json_decode($job, true);
        }
    }

    /**
     * Get a statistic belonging to this worker.
     *
     * @param string $stat Statistic to fetch.
     *
     * @return int Statistic value.
     */
    public function getStat($stat) {
        return Stats::get($stat . ':' . $this);
    }

    /**
     * Inject the logging object into the worker
     *
     * @param Psr\Log\LoggerInterface $logger
     */
    public function setLogger(Psr\Log\LoggerInterface $logger) {
        $this->logger = $logger;
    }
}
