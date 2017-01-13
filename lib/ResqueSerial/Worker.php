<?php


namespace ResqueSerial;


use Psr\Log\LogLevel;
use Resque_Event;
use Resque_Job;
use Resque_Job_DirtyExitException;
use Resque_Job_Status;
use Resque_Stat;
use ResqueSerial\Init\GlobalConfig;
use ResqueSerial\Init\WorkerConfig;
use ResqueSerial\JobStrategy\Serial;
use ResqueSerial\Serial\QueueImage;
use ResqueSerial\Serial\SerialWorkerImage;

class Worker extends \Resque_Worker {

    const SLEEP_SECONDS = 1;
    /** @var WorkerConfig */
    private $config;
    /** @var WorkerImage */
    public $image;

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

    public function pruneDeadWorkers() {
        // NOOP
    }

    public function registerWorker() {
        $this->image->addToPool()->setStartedNow();
    }

    public function unregisterWorker() {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new Resque_Job_DirtyExitException);
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

    public function workingOn(Resque_Job $job) {
        $job->worker = $this;
        $this->currentJob = $job;
        $job->updateStatus(Resque_Job_Status::STATUS_RUNNING);
        $data = json_encode(array(
                'queue' => $job->queue,
                'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y'),
                'payload' => $job->payload
        ));
        $this->image->setState($data);
    }

    /**
     * @param $job
     */
    protected function processJob(Resque_Job $job) {
        if (!$this->isJobSerial($job)) {
            parent::processJob($job);

            return;
        }

        $lock = (new QueueImage($job->getArguments()['serialQueue']))->newLock();

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
        }

        $this->doneWorking();
    }

    private function createStrategy($job) {
        if ($this->isJobSerial($job)) {
            return new Serial($this);
        }

        return $this->jobStrategy;
    }

    /**
     * @param Resque_Job $job
     *
     * @return bool
     */
    private function isJobSerial(Resque_Job $job) {
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
}