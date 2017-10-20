<?php


namespace Resque\Config;


class WorkerConfig {

    /** @var int */
    private $workerCount;
    /** @var bool */
    private $blocking;
    /** @var int */
    private $interval = 3;

    public function __construct($data) {
        if (!$this->hasRequiredFields($data)) {
            throw new \Exception("Worker configuration incomplete.");
        }
        $this->workerCount = $data['worker_count'];
        $this->blocking = $data['blocking'];
        $this->interval = isset($data['interval']) ? $data['interval'] : $this->interval;
    }

    /**
     * @return bool
     */
    public function getBlocking() {
        return (bool)$this->blocking;
    }

    /**
     * @return int
     */
    public function getInterval() {
        return (int)$this->interval;
    }

    /**
     * @return int
     */
    public function getWorkerCount() {
        return (int)$this->workerCount;
    }

    /**
     * @param $data
     *
     * @return bool
     */
    private function hasRequiredFields($data) {
        return isset($data['worker_count']) || isset($data['blocking']);
    }
}