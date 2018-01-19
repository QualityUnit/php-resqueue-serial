<?php

namespace Resque\Process;

class BaseProcessImage implements ProcessImage {

    /** @var string */
    private $id;
    /** @var string */
    private $pid;
    /** @var string */
    private $hostname;

    /**
     * @param $id
     * @param $hostname
     * @param $pid
     */
    protected function __construct($id, $hostname, $pid) {
        $this->id = $id;
        $this->pid = $pid;
        $this->hostname = $hostname;
    }

    /**
     * @return self
     */
    public static function create() {
        $hostName = gethostname();
        $pid = getmypid();

        return new self("$hostName~$pid", $hostName, $pid);
    }

    public static function fromId($processId) {
        list($hostname, $pid) = explode('~', $processId, 2);

        return new self($processId, $hostname, $pid);
    }

    /**
     * @return string
     */
    public function getHostname() {
        return $this->hostname;
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
        return gethostname() === $this->getHostname();
    }
}