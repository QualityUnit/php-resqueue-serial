<?php


namespace Resque\Worker;


use Resque\Config\GlobalConfig;

abstract class WorkerImageBase implements IWorkerImage {

    /** @var string */
    protected $id;
    /** @var string */
    protected $nodeId;
    /** @var int */
    protected $pid;
    /** @var string */
    protected $queue;

    /**
     * Creates new worker image.
     *
     * @param $queue
     *
     * @return static
     */
    public static function create($queue) {
        $worker = new static();
        $worker->nodeId = GlobalConfig::getInstance()->getNodeId();
        $worker->pid = getmypid();
        $worker->queue = $queue;
        $worker->id = GlobalConfig::getInstance()->getNodeId() . '~' . getmypid() . '~' . $queue;

        return $worker;
    }

    /**
     * Creates worker image from id.
     *
     * @param $workerId
     *
     * @return static
     */
    public static function fromId($workerId) {
        $parts = explode('~', $workerId, 3);

        $worker = new static();
        $worker->id = $workerId;
        $worker->nodeId = @$parts[0];
        $worker->pid = @$parts[1];
        $worker->queue = @$parts[2];

        return $worker;
    }

    /**
     * @return string
     */
    public function getNodeId() {
        return $this->nodeId;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getPid() {
        return $this->pid;
    }

    /**
     * @return string
     */
    public function getQueue() {
        return $this->queue;
    }

    /**
     * @return bool true if process with workers PID exists on this machine
     */
    public function isAlive() {
        return posix_getpgid($this->getPid()) > 0;
    }

    /**
     * @return bool true if worker belongs to this machine
     */
    public function isLocal() {
        return GlobalConfig::getInstance()->getNodeId() === $this->getNodeId();
    }
}