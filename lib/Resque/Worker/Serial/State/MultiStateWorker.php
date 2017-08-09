<?php

namespace ResqueSerial\Resque\Worker\Serial\State;


use Resque\Config\GlobalConfig;
use Resque\Job\Job;
use Resque\Job\Processor\IProcessor;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\Reservations\IStrategy;
use Resque\Log;
use Resque\Queue\QueueLock;
use Resque\Queue\SerialQueue;
use Resque\SignalHandler;
use Resque\Worker\Serial\SerialWorkerImage;
use Resque\Worker\WorkerBase;


class MultiStateWorker extends WorkerBase {

    /** @var IProcessor */
    private $processor;
    /** @var QueueLock */
    private $lock;
    /** @var bool */
    private $isShutDown = false;

    /**
     * @param SerialQueue $queue
     * @param IStrategy $strategy
     * @param SerialWorkerImage $image
     * @param QueueLock $lock
     */
    public function __construct(SerialQueue $queue, IStrategy $strategy, SerialWorkerImage $image,
            QueueLock $lock) {
        parent::__construct($queue, $strategy, $image);
        $this->processor = new StandardProcessor();
        $this->lock = $lock;
    }

    public function reload() {
        GlobalConfig::reload();
        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix($this->getImage()->getId()));
    }

    public function shutdown() {
        $this->isShutDown = true;
    }

    public function work() {
        $this->registerSignalHandlers();
        parent::work();
        $this->unregisterSignalHandlers();
    }

    protected function canRun() {
        if (!$this->lock->acquire()) {
            Log::error("Failed to acquire lock.");

            return false;
        }

        return !$this->isShutDown;
    }

    /**
     * @param Job $job
     *
     * @return IProcessor
     */
    protected function resolveProcessor(Job $job) {
        return $this->processor;
    }

    private function registerSignalHandlers() {
        SignalHandler::instance()->unregisterAll()
                ->register(SIGTERM, [$this, 'shutdown'])
                ->register(SIGINT, [$this, 'shutdown'])
                ->register(SIGQUIT, [$this, 'shutdown'])
                ->register(SIGHUP, [$this, 'reload']);
    }

    private function unregisterSignalHandlers() {
        SignalHandler::instance()->unregisterAll();
    }
}