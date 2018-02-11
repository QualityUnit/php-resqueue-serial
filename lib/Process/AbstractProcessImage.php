<?php

namespace Resque\Process;

use Resque\Config\GlobalConfig;

abstract class AbstractProcessImage implements IProcessImage {

    /** @var string */
    private $id;
    /** @var string */
    private $pid;
    /** @var string */
    private $nodeId;

    /**
     * @param string $id
     * @param string $nodeId
     * @param string $pid
     */
    protected function __construct($id, $nodeId, $pid) {
        $this->id = $id;
        $this->pid = $pid;
        $this->nodeId = $nodeId;
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
     * @return string
     */
    public function getPid() {
        return $this->pid;
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

    abstract public function unregister();

    abstract public function register();
}