<?php

namespace Resque\Process;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Process;
use Resque\SignalHandler;

abstract class AbstractProcess implements StandaloneProcess {

    /** @var bool */
    private $isShutDown = false;
    /** @var ProcessImage */
    private $image;
    /** @var string */
    private $title;

    /**
     * @param string $title
     * @param ProcessImage $image
     */
    public function __construct($title, ProcessImage $image) {
        $this->image = $image;
        $this->title = $title;
    }


    /**
     * @return ProcessImage
     */
    final public function getImage() {
        return $this->image;
    }

    final public function initLogger() {
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix(getmypid() . '-' . $this->title);
    }

    final public function register() {
        Process::setTitlePrefix($this->title);
        Process::setTitle('Initializing');
        $this->initLogger();

        try {
            $this->init();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to initialize ' . getmypid() . "-{$this->title}.", 0, $e);
        }

        SignalHandler::instance()
            ->unregisterAll()
            ->register(SIGTERM, [$this, 'shutDown'])
            ->register(SIGINT, [$this, 'shutDown'])
            ->register(SIGQUIT, [$this, 'shutDown'])
            ->register(SIGHUP, [$this, 'reloadAll'])
            ->register(SIGUSR1, [$this, 'initLogger']);

        Log::notice('Initialization complete.');
    }

    public function reloadAll() {
        Log::notice('Reloading');
        GlobalConfig::reload();
        $this->initLogger();

        $this->load();

        Log::notice('Reloaded');
    }

    final public function shutDown() {
        $this->isShutDown = true;
        Log::info('Shutting down');
    }

    final public function unregister() {
        Process::setTitle('Shutting down');

        $this->deinit();

        SignalHandler::instance()->unregisterAll();
        Log::notice('Ended');
    }

    /**
     * The primary loop for a worker.
     * Every $interval (seconds), the scheduled queue will be checked for jobs
     * that should be pushed to Resque.
     */
    public function work() {
        Process::setTitle('Working');

        $this->prepareWork();
        while ($this->canRun()) {

            $this->doWork();

        }
    }

    abstract protected function deinit();

    abstract protected function doWork();

    abstract protected function init();

    abstract protected function load();

    abstract protected function prepareWork();

    /**
     * @return bool
     */
    private function canRun() {
        SignalHandler::dispatch();

        return !$this->isShutDown;
    }
}