<?php


namespace Resque\Worker\Serial\State;


use Resque\Config\GlobalConfig;
use Resque\Job\Reservations\BlockingStrategy;
use Resque\Job\Reservations\IStrategy;
use Resque\Job\Reservations\SleepStrategy;
use Resque\Log;
use Resque\Process;
use Resque\Queue\QueueLock;
use Resque\Queue\SerialQueue;
use Resque\Queue\SerialQueueImage;
use Resque\SignalHandler;
use Resque\Stats\SerialQueueStats;
use Resque\Worker\Serial\SerialWorkerImage;
use ResqueSerial\Resque\Worker\Serial\State\MultiStateStrategy;
use ResqueSerial\Resque\Worker\Serial\State\MultiStateWorker;

class MultiState implements ISerialWorkerState {

    /** @var SerialQueueImage */
    private $queueImage;
    /** @var int[] */
    private $children = [];
    /** @var SerialWorkerImage */
    private $image;
    /** @var bool */
    private $isShutDown = false;
    /** @var QueueLock */
    private $lock;

    /**
     * Multi constructor.
     *
     * @param SerialQueueImage $queueImage
     * @param SerialWorkerImage $workerImage
     * @param QueueLock $lock
     */
    public function __construct(SerialQueueImage $queueImage, SerialWorkerImage $workerImage,
            QueueLock $lock) {
        Process::setTitlePrefix('serial-multi');

        $this->queueImage = $queueImage;
        $this->image = $workerImage;
        $this->lock = $lock;
    }

    public function shutdown() {
        $this->isShutDown = true;
        Log::info('Shutting down.');

        foreach ($this->children as $child) {
            posix_kill($child, SIGTERM);
        }
    }

    function work() {
        if (!$this->lock->acquire()) {
            Log::critical("Failed to acquire lock.");
            return;
        }
        if ($this->isShutDown) {
            return;
        }

        Log::info("Starting.");
        $this->image->addToPool()->setStartedNow();
        Process::setTitle('Forking runners');
        $this->forkChildren();
        Process::setTitle('Managing runners');

        while (true) {
            foreach ($this->children as $pid) {
                $response = pcntl_waitpid($pid, $status, WNOHANG);
                if ($pid == $response) {
                    unset($this->children[$pid]);
                }
            }

            if (count($this->children) == 0) {
                break;
            }

            SignalHandler::dispatch();
            sleep(1);
        }

        $this->image->removeFromPool()->clearStarted();
        Log::info("Ended.");
    }

    private function forkChildren() {
        //TODO: check if some workers already exist
        $currentConfig = $this->queueImage->config()->getCurrent();
        $queueCount = $currentConfig->getQueueCount();
        for ($i = 0; $i < $queueCount; $i++) {
            $subQueue = $this->queueImage->getSubQueue($currentConfig->getQueuePostfix($i));
            $this->forkSingleWorker($i, $subQueue);
        }
    }

    private function forkSingleWorker($id, SerialQueue $subQueue) {
        $childPid = Process::fork();

        if ($childPid === 0) { // IN CHILD
            Process::setTitlePrefix("serial-multi-{$this->image->getPid()}-sub$id");
            $this->initSubWorkerLogger($subQueue->toString());
            $childImage = SerialWorkerImage::create($subQueue->toString());
            $worker = new MultiStateWorker(
                    $subQueue,
                    $this->resolveSubWorkerStrategy(),
                    $childImage,
                    $this->lock
            );

            $this->image->addSubWorker($childImage->getId());
            $worker->work();
            $this->image->removeSubWorker($childImage->getId());
            exit(0);
        }

        $this->children[$childPid] = $childPid;
    }

    private function initSubWorkerLogger($queue) {
        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix(posix_getpid() . "-serial-$queue"));
    }

    /**
     * @return IStrategy
     */
    private function resolveSubWorkerStrategy() {
        $workerConfig = GlobalConfig::getInstance()
                ->getWorkerConfig($this->queueImage->getParentQueue());
        if ($workerConfig->getBlocking()) {
            $strategy = new BlockingStrategy($workerConfig->getInterval());
        } else {
            $strategy = new SleepStrategy($workerConfig->getInterval());
        }

        return new MultiStateStrategy($strategy, $this->queueImage->config());
    }
}
