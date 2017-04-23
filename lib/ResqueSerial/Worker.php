<?php


namespace ResqueSerial;


use Psr\Log\LogLevel;
use Resque_Event;
use Resque_Stat;
use ResqueSerial\Init\GlobalConfig;
use ResqueSerial\Init\WorkerConfig;
use ResqueSerial\Job\DirtyExitException;
use ResqueSerial\Job\Status;
use ResqueSerial\JobStrategy\Serial;
use ResqueSerial\Serial\SerialQueueImage;

class Worker extends \Resque_Worker {

    const SLEEP_SECONDS = 1;
    /** @var WorkerConfig */
    private $config;
    /** @var WorkerImage */
    private $image;

    /**
     * @inheritdoc
     *
     * @param GlobalConfig $config
     */
    public function __construct($queues, $config) {
        parent::__construct($queues);
        $queueString = implode(',', $queues);
        $this->image = WorkerImage::create($queueString);
        $this->config = $config->getWorkerConfig($queueString);
        $this->setId($this->image->getId());
    }

    public function doneWorking() {
        $jobDone = $this->currentJob;
        $this->currentJob = null;
        if (!$this->isJobSerial($jobDone)) {
            Resque_Stat::incr('processed');
            $this->image->incStat('processed')->clearState();
        }

        while ($this->isSerialLimitReached()) {
            sleep(self::SLEEP_SECONDS);
        }
    }

    /**
     * @return WorkerImage
     */
    public function getImage() {
        return $this->image;
    }

    public function pruneDeadWorkers() {
        // NOOP
    }

    public function registerWorker() {
        $this->image
                ->addToPool()
                ->setStartedNow();
    }

    public function unregisterWorker() {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new DirtyExitException);
        }

        $this->image
                ->removeFromPool()
                ->clearState()
                ->clearStarted()
                ->clearStat('processed')
                ->clearStat('failed');
    }

    public function updateProcLine($status) {
        $processTitle = "resque-serial-worker: $status";
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        } else if (function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    public function workingOn(ResqueJob $job) {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(Status::STATUS_RUNNING);
        $data = json_encode(array(
                'queue' => $job->queue,
                'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                'payload' => $job->payload
        ));
        $this->image->setState($data);
    }

    protected function initReserveStrategy($interval, $blocking) {
        // use config for blocking parameter
        parent::initReserveStrategy($interval, $this->config->getBlocking());
    }

    /**
     * @param $job
     */
    protected function processJob(ResqueJob $job) {
        if (!$this->isJobSerial($job)) {
            parent::processJob($job);

            return;
        }

        $lock = SerialQueueImage::fromName($job->getArguments()['serialQueue'])->newLock();

        if (!$lock->acquire()) {
            $this->logger->log(LogLevel::INFO, 'Nothing to do with job {job}', array('job' => $job));

            return;
        }

        $this->logger->log(LogLevel::NOTICE, 'Starting work on {job}', array('job' => $job));
        Resque_Event::trigger('beforeFork', $job);
        $this->workingOn($job);

        $job->setTaskFactory(new SerialTaskFactory($lock));

        try {
            $this->createStrategy($job)->perform($job);
        } catch (ForkException $e) {
            $lock->release();
            $this->logger->critical("Failed to create fork to perform job " . @$job->payload['id']);
            $job->fail($e);
        }

        $this->doneWorking();
    }

    protected function reload() {
        $this->logger->debug("Reloading configuration");
        GlobalConfig::reload();
        $config = GlobalConfig::instance()->getWorkerConfig($this->image->getQueue());

        if ($config == null) {
            $this->logger->error("Failed to reload configuration - queue section missing.");

            return;
        }

        $this->config = $config;
        Resque_Event::trigger('reload', $this);
    }

    protected function startup() {
        $this->registerSigHandlers();
        Resque_Event::trigger('beforeFirstFork', $this);
        $this->registerWorker();
    }

    private function createStrategy($job) {
        if ($this->isJobSerial($job)) {
            return new Serial($this);
        }

        return $this->jobStrategy;
    }

    /**
     * @param ResqueJob $job
     *
     * @return bool
     */
    private function isJobSerial(ResqueJob $job) {
        if ($job == null) {
            return false;
        }

        return $job->getTaskClass() === SerialTaskFactory::SERIAL_CLASS;
    }

    /**
     * @return boolean
     */
    private function isSerialLimitReached() {
        $count = \Resque::redis()->scard(Key::workerSerialWorkers((string)$this));

        return $count >= $this->config->getMaxSerialWorkers();
    }

    /**
     * Register signal handlers that a worker should respond to.
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers() {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGQUIT, [$this, 'shutdown']);
            pcntl_signal(SIGHUP, [$this, 'reload']);
            $this->logger->debug('Registered signals');
        }
    }
}