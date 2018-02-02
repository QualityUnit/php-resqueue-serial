<?php


namespace Resque\Config;


/**
 * @deprecated REMOVE THIS
 * Class WorkerConfig
 */
class WorkerConfig {

    /** @var int */
    private $workerCount;
    /** @var bool */
    private $blocking;
    /** @var int */
    private $interval = 3;

    /**
     * @deprecated
     *
     * @param $data
     *
     * @throws \Exception
     */
    public function __construct($data) {
        if (!$this->hasRequiredFields($data)) {
            throw new \Exception("Worker configuration incomplete.");
        }
        $this->workerCount = $data['worker_count'];
        $this->blocking = $data['blocking'];
        $this->interval = isset($data['interval']) ? $data['interval'] : $this->interval;
    }

    /**
     * @deprecated
     * @return bool
     */
    public function getBlocking() {
        return (bool)$this->blocking;
    }

    /**
     * @deprecated
     * @return int
     */
    public function getInterval() {
        return (int)$this->interval;
    }

    /**
     * @deprecated
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