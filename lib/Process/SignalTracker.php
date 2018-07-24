<?php

namespace Resque\Process;

use Resque\Process;
use Resque\SignalHandler;

class SignalTracker {

    /** @var int */
    private $trackedSignal;
    /** @var string[] */
    private $receivedSignals = [];
    /** @var callable|int|null */
    private $originalHandler;
    /** @var int|null */
    private $startTime;

    public function __construct($trackedSignal) {
        $this->trackedSignal = $trackedSignal;
    }

    public function getStartTime() {
        return $this->startTime;
    }

    public function receivedFrom($pid) {
        SignalHandler::dispatch();

        return array_key_exists($pid, $this->receivedSignals);
    }

    public function register() {
        if ($this->originalHandler !== null) {
            throw new \RuntimeException("Can't track more signals simultaneously.");
        }
        $this->originalHandler = SignalHandler::instance()->getHandler($this->trackedSignal);

        SignalHandler::instance()->register($this->trackedSignal, function ($sigNo, $sigInfo) {
            $pid = $sigInfo['pid'] ?? false;
            if ($pid) {
                $this->receivedSignals[$pid] = $pid;
            }
        });
        $this->startTime = microtime(true);
    }

    public function unregister() {
        if ($this->originalHandler === null) {
            return;
        }
        $this->receivedSignals = [];
        SignalHandler::instance()->register($this->trackedSignal, $this->originalHandler);
        $this->originalHandler = null;
        $this->startTime = null;
    }

    /**
     * @param int $pid
     * @param int $timeoutSeconds
     *
     * @return bool
     * @throws \Exception
     */
    public function waitFor($pid, $timeoutSeconds) {
        pcntl_sigtimedwait([$this->trackedSignal], $sigInfo, $timeoutSeconds);

        if ($sigInfo === null && !Process::isPidAlive($pid)) {
            if ($this->receivedFrom($pid)) {
                return true;
            }

            throw new \Exception('Job process ended without signalling success.');
        }

        return $sigInfo !== null;
    }
}