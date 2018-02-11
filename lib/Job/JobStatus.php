<?php

namespace Resque\Job;

use Resque\Key;
use Resque\Protocol\Job;
use Resque\Resque;

class JobStatus {

    const STATUS_WAITING = 'waiting';
    const STATUS_RUNNING = 'running';
    const STATUS_FAILED = 'failed';
    const STATUS_RETRIED = 'retried';
    const STATUS_FINISHED = 'finished';

    /** @var string */
    private $id;
    /** @var boolean */
    private $isMonitored;

    /**
     * @param Job $job
     * @param string $id
     */
    public function __construct(Job $job, $id) {
        $this->id = $id;
        $this->isMonitored = $job->isMonitored();
    }

    public function setFailed() {
        $this->updateStatus(self::STATUS_FAILED);
    }

    public function setFinished() {
        $this->updateStatus(self::STATUS_FINISHED);
    }

    public function setRetried() {
        $this->updateStatus(self::STATUS_RETRIED);
    }

    public function setRunning() {
        $this->updateStatus(self::STATUS_RUNNING);
    }

    public function setWaiting() {
        $this->updateStatus(self::STATUS_WAITING);
    }

    private function updateStatus($status) {
        if (!$this->isMonitored) {
            return;
        }

        Resque::redis()->setEx(Key::jobStatus($this->id), 86400, json_encode([
            'status' => $status,
            'updated' => time(),
        ]));
    }
}