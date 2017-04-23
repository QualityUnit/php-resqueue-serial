<?php


namespace ResqueSerial\Serial;


use Psr\Log\LoggerInterface;
use ResqueSerial\EventBus;
use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\QueueLock;
use ResqueSerial\WorkerImage;

class SerialWorker {

    const RECOMPUTE_CONFIG_EVENT = "recomputeConfig";

    /** @var IWorker */
    private $state;
    /** @var SerialQueueImage */
    private $queue;
    /** @var SerialWorkerImage */
    private $image;
    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $stopping = false;
    /** @var QueueLock */
    private $lock;

    /**
     * SerialWorker constructor.
     *
     * @param $serialQueue
     * @param QueueLock $lock
     */
    public function __construct($serialQueue, QueueLock $lock) {
        $this->queue = SerialQueueImage::fromName($serialQueue);
        $this->image = SerialWorkerImage::create($serialQueue);
        $this->logger = Log::prefix($this->image->getPid() . "-serial_worker-$serialQueue");
        $this->lock = $lock;
    }

    public function getId() {
        return $this->image->getId();
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger() {
        return $this->logger;
    }

    public function recompute() {
        // TODO implement once we have the need for it (manual configuration now)
    }

    public function shutdown() {
        $this->stopping = true;
        $this->logger->notice("Shutting down");

        if ($this->state == null) {
            return;
        }

        $this->state->shutdown();
    }

    public function work($parentWorkerId) {
        if (!$this->lock->acquire()) {
            $this->logger->warning("Failed to reacquire lock before startup. Halting...");

            return;
        }
        $this->logger->notice("Starting.");
        // register
        $this->registerSigHandlers();
        WorkerImage::fromId($parentWorkerId)->addSerialWorker($this->getId());
        $this->image
                ->setParent($parentWorkerId)
                ->addToPool()
                ->setStartedNow();

        $recompute = [$this, 'recompute'];
        EventBus::listen(self::RECOMPUTE_CONFIG_EVENT, $recompute);

        // do work
        $this->state = $this->changeStateFromConfig();
        $this->recompute();
        try {
            while (true) {
                if (!$this->lock->acquire()) {
                    $this->logger->critical("Failed to reacquire lock before work. Halting...");
                    $this->shutdown();
                    break;
                }
                $this->state->work();

                if ($this->allSubQueuesEmpty()) {
                    $this->queue->config()->removeCurrent();
                }

                if ($this->isToBeTerminated()) {
                    break;
                }

                $this->state = $this->changeStateFromConfig();
            }
        } catch (\Exception $e) {
            $this->logger->error("Serial worker encountered an exception. {exception}", ['exception' => $e]);
            $this->shutdown();
        }

        // unregister
        EventBus::stopListening(self::RECOMPUTE_CONFIG_EVENT, $recompute);
        WorkerImage::fromId($this->image->getParent())->removeSerialWorker($this->getId());
        $this->image
                ->clearParent()
                ->removeFromPool()
                ->clearState()
                ->clearStarted()
                // in case of single state
                ->clearStat('processed')
                ->clearStat('failed');

        $this->logger->notice('Ended.');
    }

    private function allSubQueuesEmpty() {
        $current = $this->queue->config()->getCurrent();

        if ($current->getQueueCount() > 1) {
            for ($i = 0; $i < $current->getQueueCount(); $i++) {
                $queue = $this->queue->getQueue() . $current->getQueuePostfix($i);
                if (\Resque::redis()->llen(Key::serialQueue($queue)) > 0) {
                    return false;
                }
            }

            return true;
        }

        return \Resque::redis()->llen(Key::serialQueue($this->queue->getQueue())) == 0;
    }

    private function changeStateFromConfig() {
        if ($this->queue->config()->getQueueCount() == 1) {
            $single = new Single($this->queue->getQueue());
            $single->setId($this->image->getId());
            $single->setLock($this->lock);

            return $single;
        } else {
            $multi = new Multi($this->queue->getQueue(), $this->queue->config(), $this->lock);
            $multi->setImage($this->image);

            return $multi;
        }
    }

    private function isToBeTerminated() {
        return $this->queue->config()->isEmpty() || $this->stopping;
    }

    private function registerSigHandlers() {
        $this->image->addToPool()->setStartedNow();

        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGQUIT, [$this, 'shutdown']);
        $this->logger->debug('Registered signals');
    }
}