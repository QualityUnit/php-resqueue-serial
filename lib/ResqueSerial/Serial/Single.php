<?php


namespace ResqueSerial\Serial;


use Resque;
use Resque_Event;
use Resque_Job;
use Resque_Job_Status;
use Resque_Stat;
use ResqueSerial\Key;
use ResqueSerial\Log;

class Single extends \Resque_Worker implements IWorker {

    /** @var bool */
    private $isParallel;
    /** @var SerialWorkerImage */
    private $image;

    public function __construct($queue, $isParallel = false) {
        parent::__construct([$queue]);
        $this->isParallel = $isParallel;
        $this->image = SerialWorkerImage::create($queue);
        $this->setId($this->image->getId());
        $this->setLogger(Log::prefix(getmypid() . "-single-$queue"));
    }

    public function doneWorking() {
        $this->currentJob = null;
        Resque_Stat::incr('processed');
        $this->image
                ->incStat('processed')
                ->clearState();
    }

    public function pruneDeadWorkers() {
        // NOOP
    }

    public function queues($fetch = true) {
        return new SerialQueue($this->queues);
    }

    public function registerWorker() {
        // NOOP - workers creating single instances are responsible for registering them
    }

    public function unregisterWorker() {
        if (is_object($this->currentJob)) {
            $this->currentJob->fail(new \Resque_Job_DirtyExitException);
        }

        if ($this->isParallel && function_exists('pcntl_signal')) {
            // unregister handlers (ignore)
            pcntl_signal(SIGTERM, SIG_IGN);
            pcntl_signal(SIGINT, SIG_IGN);
            pcntl_signal(SIGQUIT, SIG_IGN);

            $this->logger->debug('Unregistered signals.');
        }

        $this->logger->notice('Ended.');
    }

    public function updateProcLine($status) {
        $processTitle = "resque-serial-serial_worker: $status";
        if(function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        }
        else if(function_exists('setproctitle')) {
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

    protected function initReserveStrategy($interval, $blocking) {
        $this->reserveStrategy = new TerminateStrategy($this, 1, $this->isParallel);
    }

    protected function processJob(Resque_Job $job) {
        parent::processJob($job);
        Resque::redis()->incr(Key::serialCompletedCount($job->queue));
        Resque_Event::trigger(SerialWorker::RECOMPUTE_CONFIG_EVENT, $this);
    }

    protected function startup() {
        $this->registerSigHandlers();
        $this->registerWorker();
    }

    /**
     * Register signal handlers that a worker should respond to.
     * TERM: Shutdown after the current job finishes processing.
     * INT: Shutdown after the current job finishes processing.
     * QUIT: Shutdown after the current job finishes processing.
     */
    private function registerSigHandlers() {
        if ($this->isParallel && function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
            pcntl_signal(SIGQUIT, [$this, 'shutdown']);
            $this->logger->debug('Registered signals');
        }
    }
}