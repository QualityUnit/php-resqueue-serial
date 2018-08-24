<?php


namespace Resque\Init;


use ReflectionClass;
use Resque\Config\ConfigException;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Maintenance\AllocatorMaintainer;
use Resque\Maintenance\BatchPoolMaintainer;
use Resque\Maintenance\IProcessMaintainer;
use Resque\Maintenance\SchedulerMaintainer;
use Resque\Maintenance\StaticPoolMaintainer;
use Resque\Process;
use Resque\Resque;
use Resque\SignalHandler;
use Resque\StatsD;

class InitProcess {

    /** @var IProcessMaintainer[] */
    private $maintainers = [];

    private $stopping = false;
    private $reloaded = false;

    public function maintain() {
        Process::setTitle('maintaining');
        while (true) {
            sleep(5);
            SignalHandler::dispatch();
            if ($this->stopping) {
                break;
            }
            $this->recover();
        }
    }

    public function recover() {
        foreach ($this->maintainers as $maintainer) {
            $className = (new ReflectionClass($maintainer))->getShortName();
            Log::info("=== Maintenance started ($className)");
            $maintainer->maintain();
        }
    }

    public function reload() {
        Log::debug('Reloading configuration');
        GlobalConfig::reload();
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix('init-process');
        $this->initializeMaintainers();
        $this->reloaded = true;

        $this->signalProcesses(SIGHUP, 'HUP');
    }

    /**
     * send TERM to all workers and serial workers
     */
    public function shutdown() {
        $this->stopping = true;

        $this->signalProcesses(SIGTERM, 'TERM');
    }

    public function start() {
        Process::setTitlePrefix('init');
        Process::setTitle('starting');
        $this->initialize();
        $this->recover();
    }

    private function initialize() {
        Resque::setBackend(GlobalConfig::getInstance()->getBackend());

        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix('init-process');
        $this->initializeMaintainers();

        $this->registerSigHandlers();
    }

    private function initializeMaintainers() {
        unset($this->maintainers);
        $this->maintainers = [];

        foreach (GlobalConfig::getInstance()->getStaticPoolConfig()->getPoolNames() as $poolName) {
            try {
                $this->maintainers[] = new StaticPoolMaintainer($poolName);
            } catch (ConfigException $e) {
                Log::error("Failed to initialize static pool $poolName maintainer.", [
                    'exception' => $e
                ]);
            }
        }

        foreach (GlobalConfig::getInstance()->getBatchPoolConfig()->getPoolNames() as $poolName) {
            try {
                $this->maintainers[] = new BatchPoolMaintainer($poolName);
            } catch (ConfigException $e) {
                Log::error("Failed to initialize batch pool $poolName maintainer.", [
                    'exception' => $e
                ]);
            }
        }

        $this->maintainers[] = new AllocatorMaintainer();

        $this->maintainers[] = new SchedulerMaintainer();
    }

    private function registerSigHandlers() {
        SignalHandler::instance()->unregisterAll()
            ->register(SIGTERM, [$this, 'shutdown'])
            ->register(SIGINT, [$this, 'shutdown'])
            ->register(SIGQUIT, [$this, 'shutdown'])
            ->register(SIGHUP, [$this, 'reload'])
            ->register(SIGUSR1,  function () {
                Log::warning('Received unhandled SIGUSR1.');
            })
            ->register(SIGUSR2,  function () {
                Log::warning('Received unhandled SIGUSR2.');
            })
            ->register(SIGCHLD, SIG_IGN); // prevent zombie children by ignoring them
        Log::debug('Registered signals');
    }

    private function signalProcesses($signal, $signalName) {
        foreach ($this->maintainers as $maintainer) {
            foreach ($maintainer->getLocalProcesses() as $localProcess) {
                Log::debug("Signalling $signalName to {$localProcess->getId()}");
                posix_kill($localProcess->getPid(), $signal);
            }
        }

    }
}