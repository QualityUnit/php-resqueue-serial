<?php

namespace Resque\Maintenance;

use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\Process\BaseProcessImage;
use Resque\Process\ProcessImage;
use Resque\Scheduler\SchedulerProcess;
use Resque\SignalHandler;

class SchedulerMaintainer implements ProcessMaintainer {

    /**
     * @return ProcessImage[]
     */
    public function getLocalProcesses() {
        $scheduleIds = \Resque::redis()->sMembers(Key::localSchedulerProcesses());
        $images = [];

        foreach ($scheduleIds as $processId) {
            $images[] = BaseProcessImage::fromId($processId);
        }

        return $images;
    }

    /**
     * Cleans up and recovers local processes.
     *
     * @return void
     */
    public function maintain() {
        $schedulerIsAlive = $this->cleanupSchedulers();
        if (!$schedulerIsAlive) {
            $this->forkScheduler();
        }
    }

    /**
     * Checks all scheduler processes and keeps at most one alive.
     *
     * @return bool true if at least one scheduler process is alive after cleanup
     */
    private function cleanupSchedulers() {
        $oneAlive = false;
        foreach ($this->getLocalProcesses() as $localProcess) {
            // cleanup if dead
            if (!$localProcess->isAlive()) {
                $this->removeSchedulerRecord($localProcess->getId());
                continue;
            }
            // kill and cleanup
            if ($oneAlive) {
                Log::notice('Terminating extra scheduler process');
                posix_kill($localProcess->getPid(), SIGTERM);
                $this->removeSchedulerRecord($localProcess->getId());
                continue;
            }

            $oneAlive = true;
        }

        return $oneAlive;
    }

    private function forkScheduler() {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();
            $scheduler = new SchedulerProcess();
            try {
                $scheduler->register();
                $scheduler->work();
            } catch (\Throwable $t) {
                Log::error('Scheduler process failed.', [
                    'exception' => $t
                ]);
            } finally {
                $scheduler->unregister();
            }
            exit(0);
        }
    }

    /**
     * @param string $schedulerId
     */
    private function removeSchedulerRecord($schedulerId) {
        \Resque::redis()->sRem(Key::localSchedulerProcesses(), $schedulerId);
    }
}