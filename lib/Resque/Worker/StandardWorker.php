<?php


namespace Resque\Worker;


use Resque\Api\Job;
use Resque\Config\GlobalConfig;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\Reservations\BlockingStrategy;
use Resque\Job\Reservations\IStrategy;
use Resque\Job\Reservations\SleepStrategy;
use Resque\Log;
use Resque\Process;
use Resque\Queue\Queue;
use Resque\SignalHandler;
use Resque\StatsD;

class StandardWorker extends WorkerBase {

    private $isShutDown = false;
    private $standardProc = null;

    /**
     * @param string $queue
     */
    public function __construct($queue) {
        Process::setTitlePrefix('worker');
        $this->initLogger($queue);

        parent::__construct(
                new Queue($queue),
                $this->resolveStrategy($queue),
                WorkerImage::create($queue)
        );

        $this->standardProc = new StandardProcessor();
    }

    /**
     * @return WorkerImage
     */
    public function getImage() {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getImage();
    }

    /**
     * Schedule the worker for reload. Will finish processing the current job
     * and reload the configuration.
     */
    public function reload() {
        Log::notice('Reloading');
        GlobalConfig::reload();
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
        $this->initLogger($this->getImage()->getQueue());
        $this->setStrategy($this->resolveStrategy($this->getImage()->getQueue()));
        Log::notice('Reloaded');
    }

    public function reloadLogger() {
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown() {
        $this->isShutDown = true;
        Log::notice('Shutting down');
    }

    public function work() {
        $this->getImage()->addToPool();
        $this->registerSigHandlers();

        parent::work();

        $this->unregisterSigHandlers();
        $this->getImage()->removeFromPool();
    }

    protected function canRun() {
        SignalHandler::dispatch();

        return !$this->isShutDown;
    }

    protected function resolveProcessor(Job $job) {
        return $this->standardProc;
    }

    private function initLogger($queue) {
        $this->reloadLogger();
        Log::setPrefix(posix_getpid() . "-worker-$queue");
    }

    private function registerSigHandlers() {
        SignalHandler::instance()->unregisterAll()
                ->register(SIGTERM, [$this, 'shutdown'])
                ->register(SIGINT, [$this, 'shutdown'])
                ->register(SIGQUIT, [$this, 'shutdown'])
                ->register(SIGHUP, [$this, 'reload'])
                ->register(SIGUSR1, [$this, 'reloadLogger']);
        Log::debug('Registered signals');
    }

    /**
     * @param $queue
     *
     * @return IStrategy
     */
    private function resolveStrategy($queue) {
        $workerConfig = GlobalConfig::getInstance()->getWorkerConfig($queue);
        if ($workerConfig->getBlocking()) {
            $strategy = new BlockingStrategy($workerConfig->getInterval());
        } else {
            $strategy = new SleepStrategy($workerConfig->getInterval());
        }

        return $strategy;
    }

    private function unregisterSigHandlers() {
        SignalHandler::instance()->unregisterAll();
        Log::debug('Unregistered signals in ' . posix_getpid());
    }
}