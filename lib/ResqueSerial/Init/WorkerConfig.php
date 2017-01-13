<?php


namespace ResqueSerial\Init;


class WorkerConfig {

    /** @var int */
    private $workerCount;
    /** @var bool */
    private $blocking;
    /** @var int */
    private $maxSerialWorkers;
    /** @var int */
    private $maxSerialSubqueues;

    public function __construct($data) {
        if (!$this->hasRequiredFields($data)) {
            throw new \Exception("Worker configuration incomplete.");
        }
        $this->workerCount = $data['worker_count'];
        $this->blocking = $data['blocking'];
        $serial = $data['serial'];
        $this->maxSerialSubqueues = $serial['max_subqueues'];
        $this->maxSerialWorkers = $serial['max_workers'];
    }

    /**
     * @return bool
     */
    public function getBlocking() {
        return $this->blocking;
    }

    /**
     * @return int
     */
    public function getMaxSerialSubqueues() {
        return $this->maxSerialSubqueues;
    }

    /**
     * @return int
     */
    public function getMaxSerialWorkers() {
        return $this->maxSerialWorkers;
    }

    /**
     * @return int
     */
    public function getWorkerCount() {
        return $this->workerCount;
    }

    /**
     * @param $data
     * @return bool
     */
    private function hasRequiredFields($data) {
        if (!isset($data['worker_count']) || !isset($data['blocking']) || !isset($data['serial'])) {
            return false;
        }
        $serial = $data['serial'];

        return isset($serial['max_subqueues']) && isset($serial['max_workers']);
    }
}