<?php


namespace Resque\Worker\Serial\State;


use Resque\Config\GlobalConfig;
use Resque\Job\Job;
use Resque\Job\Processor\IProcessor;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\Reservations\BlockingStrategy;
use Resque\Job\Reservations\IStrategy;
use Resque\Job\Reservations\SleepStrategy;
use Resque\Job\Reservations\TerminateStrategy;
use Resque\Log;
use Resque\Process;
use Resque\Queue\QueueLock;
use Resque\Queue\SerialQueue;
use Resque\Queue\SerialQueueImage;
use Resque\SignalHandler;
use Resque\Worker\Serial\SerialWorkerImage;
use Resque\Worker\WorkerBase;

class SingleState extends WorkerBase implements ISerialWorkerState {

    /** @var IProcessor */
    private $processor;
    /** @var QueueLock */
    private $lock;
    /** @var bool */
    private $isShutDown = false;

    /**
     * @param SerialQueueImage $queueImage
     * @param SerialWorkerImage $workerImage
     * @param QueueLock $lock
     */
    public function __construct(SerialQueueImage $queueImage, SerialWorkerImage $workerImage,
            QueueLock $lock) {
        Process::setTitlePrefix('resque-serial-single');

        parent::__construct(
                new SerialQueue($queueImage->getQueue()),
                $this->resolveStrategy($queueImage),
                $workerImage
        );

        $this->processor = new StandardProcessor();
        $this->lock = $lock;
    }

    public function shutdown() {
        $this->isShutDown = true;
    }

    protected function canRun() {
        if (!$this->lock->acquire()) {
            Log::error("Failed to acquire lock.");

            return false;
        }
        SignalHandler::dispatch();

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

    /**
     * @param SerialQueueImage $queueImage
     *
     * @return IStrategy
     */
    private function resolveStrategy(SerialQueueImage $queueImage) {
        $workerConfig = GlobalConfig::getInstance()
                ->getWorkerConfig($queueImage->getParentQueue());
        if ($workerConfig->getBlocking()) {
            $strategy = new BlockingStrategy($workerConfig->getInterval());
        } else {
            $strategy = new SleepStrategy($workerConfig->getInterval());
        }

        return new TerminateStrategy($strategy);
    }
}