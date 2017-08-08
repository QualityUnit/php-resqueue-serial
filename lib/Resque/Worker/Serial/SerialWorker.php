<?php


namespace Resque\Worker\Serial;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\Queue\QueueLock;
use Resque\Queue\SerialQueueImage;
use Resque\SignalHandler;
use Resque\Worker\Serial\State\ISerialWorkerState;
use Resque\Worker\Serial\State\MultiState;
use Resque\Worker\Serial\State\SingleState;


/**
 * Class SerialManager constructs and manages workers on a serial queue.
 */
class SerialWorker {

    /** @var SerialQueueImage */
    private $queue;
    /** @var QueueLock */
    private $lock;
    /** @var SerialWorkerImage */
    private $image;
    /** @var ISerialWorkerState */
    private $state;
    /** @var bool */
    private $stopping = false;

    public function __construct(SerialWorkerImage $image, $lock) {
        Process::setTitlePrefix('serial-worker');
        $this->initLogger($image->getQueue());
        $this->queue = SerialQueueImage::fromName($image->getQueue());
        $this->lock = $lock;
        $this->image = $image;
    }

    public function getId() {
        return $this->image->getId();
    }

    public function reload() {
        Log::notice('Reloading');
        GlobalConfig::reload();
        $this->initLogger($this->image->getQueue());
        Log::notice('Reloaded');
    }

    public function shutdown() {
        if ($this->stopping) {
            return;
        }

        $this->stopping = true;
        Log::notice("Shutting down");

        if ($this->state == null) {
            return;
        }

        $this->state->shutdown();
    }

    public function work($parentWorkerId) {
        if (!$this->lock->acquire()) {
            Log::error("Failed to reacquire lock before startup. Halting...");

            return;
        }
        Log::info("Starting.");
        $this->initializeWork($parentWorkerId);

        // do work
        $this->state = $this->resolveStateFromConfig();
        try {
            while (true) {
                if (!$this->lock->acquire()) {
                    Log::critical("Failed to reacquire lock before work. Halting...");
                    break;
                }
                $this->state->work();

                if ($this->allSubQueuesEmpty()) {
                    $this->queue->config()->removeCurrent();
                }

                SignalHandler::dispatch();
                if ($this->isToBeTerminated()) {
                    break;
                }

                $this->state = $this->resolveStateFromConfig();
            }
        } catch (\Exception $e) {
            Log::error("Serial worker encountered an exception. {exception}", ['exception' => $e]);
        }

        $this->shutdown();
        $this->deinitializeWork();
    }

    private function allSubQueuesEmpty() {
        $current = $this->queue->config()->getCurrent();

        if ($current->getQueueCount() > 1) {
            for ($i = 0; $i < $current->getQueueCount(); $i++) {
                $queue = $this->queue->getQueue() . $current->getQueuePostfix($i);
                if (Resque::redis()->llen(Key::serialQueue($queue)) > 0) {
                    return false;
                }
            }

            return true;
        }

        return Resque::redis()->llen(Key::serialQueue($this->queue->getQueue())) == 0;
    }

    private function deinitializeWork() {
        $this->unregisterSigHandlers();
        $this->image
                ->clearParent()
                ->removeFromPool()
                ->clearState()
                ->clearStarted();
    }

    private function initLogger($queue) {
        Log::initialize(GlobalConfig::getInstance());
        Log::setLogger(Log::prefix(posix_getpid() . "-serial-$queue"));
    }

    /**
     * @param $parentWorkerId
     */
    private function initializeWork($parentWorkerId) {
        $this->registerSigHandlers();
        $this->image
                ->setParent($parentWorkerId)
                ->addToPool()
                ->setStartedNow();
    }

    private function isToBeTerminated() {
        return $this->queue->config()->isEmpty() || $this->stopping;
    }

    private function registerSigHandlers() {
        SignalHandler::instance()
                ->unregisterAll()
                ->register(SIGTERM, [$this, 'shutdown'])
                ->register(SIGINT, [$this, 'shutdown'])
                ->register(SIGQUIT, [$this, 'shutdown'])
                ->register(SIGHUP, [$this, 'reload']);
        Log::debug('Registered signals');
    }

    /**
     * @return ISerialWorkerState
     */
    private function resolveStateFromConfig() {
        if ($this->queue->config()->getQueueCount() == 1) {
            return new SingleState($this->queue, $this->image, $this->lock);
        }

        return new MultiState($this->queue, $this->image, $this->lock);
    }

    private function unregisterSigHandlers() {
        SignalHandler::instance()->unregisterAll();
        Log::debug('Unregistered signals');
    }
}