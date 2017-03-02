<?php


namespace ResqueSerial\Serial;


use Psr\Log\LoggerInterface;
use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\SerialTask;
use ResqueSerial\WorkerImage;

class SerialWorker {

    const RECOMPUTE_CONFIG_EVENT = "recomputeConfig";

    /** @var IWorker */
    private $state;
    /** @var QueueImage */
    private $queue;
    /** @var SerialWorkerImage */
    private $image;
    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $stopping = false;

    /**
     * SerialWorker constructor.
     *
     * @param $serialQueue
     */
    public function __construct($serialQueue) {
        $this->queue = QueueImage::fromName($serialQueue);
        $this->image = SerialWorkerImage::create($serialQueue);
        $this->logger = Log::prefix($this->image->getPid() . "-serial_worker-$serialQueue");
    }

    public function getId() {
        return $this->image->getId();
    }

    public function shutdown() {
        $this->stopping = true;
        $this->logger->notice("Shutting down");

        if($this->state == null) {
            return;
        }

        $this->state->shutdown();
    }

    public function recompute() {
        // TODO implement once we have the need for it (manual configuration now)
    }

    public function work($parentWorkerId) {
        $this->logger->notice("Starting.");
        // register
        $this->registerSigHandlers();
        WorkerImage::fromId($parentWorkerId)->addSerialWorker($this->getId());
        $this->image
                ->setParent($parentWorkerId)
                ->addToPool()
                ->setStartedNow();

        $recompute = [$this, 'recompute'];
        \Resque_Event::listen(self::RECOMPUTE_CONFIG_EVENT, $recompute);

        // do work
        $this->state = $this->changeStateFromConfig();
        $this->recompute();
        try {
            while (true) {
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
        }

        // unregister
        \Resque_Event::stopListening(self::RECOMPUTE_CONFIG_EVENT, $recompute);
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

        if($current->getQueueCount() > 1) {
            for ($i = 0; $i < $current->getQueueCount(); $i++) {
                $queue = $this->queue->getQueue() . $current->getQueuePostfix($i);
                if(\Resque::redis()->llen(Key::serialQueue($queue)) > 0) {
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
            return $single;
        } else {
            $multi = new Multi($this->queue->getQueue(), $this->queue->config());
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