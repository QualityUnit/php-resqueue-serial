<?php


namespace Resque\Init;


use Resque;
use Resque\Config\GlobalConfig;
use Resque\Log;
use Resque\Maintenance\AllocatorMaintainer;
use Resque\Maintenance\BatchPoolMaintainer;
use Resque\Maintenance\IProcessMaintainer;
use Resque\Maintenance\SchedulerMaintainer;
use Resque\Maintenance\StaticPoolMaintainer;
use Resque\Process;
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
        Log::debug('========= Starting maintenance');

        foreach ($this->maintainers as $maintainer) {
            $maintainer->maintain();
        }

        Log::debug('========= Maintenance ended');
    }

    public function reload() {
        Log::debug('Reloading configuration');
        GlobalConfig::reload();
        StatsD::initialize(GlobalConfig::getInstance()->getStatsConfig());
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());
        Log::setPrefix('init-process');
        $this->reloaded = true;

        $this->signalProcesses(SIGHUP, 'HUP');
    }

    public function reloadLogger() {
        Log::debug('Reloading logger');
        Log::initialize(GlobalConfig::getInstance()->getLogConfig());

        $this->signalProcesses(SIGUSR1, 'USR1');
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
        $this->maintainers = [];

        foreach (GlobalConfig::getInstance()->getStaticPoolConfig()->getPoolNames() as $poolName) {
            $this->maintainers[] = new StaticPoolMaintainer($poolName);
        }

        foreach (GlobalConfig::getInstance()->getBatchPoolConfig()->getPoolNames() as $poolName) {
            $this->maintainers[] = new BatchPoolMaintainer($poolName);
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
            ->register(SIGUSR1, [$this, 'reloadLogger'])
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