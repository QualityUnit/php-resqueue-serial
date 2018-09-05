<?php

namespace Resque\Worker;

use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Process\AbstractProcessImage;
use Resque\Resque;

class WorkerImage extends AbstractProcessImage {

    /** @var string */
    private $poolName;
    /** @var string */
    private $code;

    protected function __construct($workerId, $nodeId, $poolName, $code, $pid) {
        parent::__construct($workerId, $nodeId, $pid);

        $this->poolName = $poolName;
        $this->code = $code;
    }

    /**
     * @param string $poolName
     * @param string $code
     *
     * @return self
     */
    public static function create($poolName, $code) {
        $nodeId = GlobalConfig::getInstance()->getNodeId();
        $pid = getmypid();
        $id = "$nodeId~$poolName~$code~$pid";

        return new self($id, $nodeId, $poolName, $code, $pid);
    }

    public static function load($workerId) {
        list($nodeId, $poolName, $code, $pid) = explode('~', $workerId, 4);

        return new self($workerId, $nodeId, $poolName, $code, $pid);
    }

    /**
     * @throws \Resque\RedisError
     */
    public function clearRuntimeInfo() {
        Resque::redis()->del(Key::workerRuntimeInfo($this->getId()));
    }

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getPoolName() {
        return $this->poolName;
    }

    /**
     * @return RuntimeInfo
     * @throws \Resque\RedisError
     */
    public function getRuntimeInfo() {
        return RuntimeInfo::fromString(Resque::redis()->get(Key::workerRuntimeInfo($this->getId())));
    }

    /**
     * @throws \Resque\RedisError
     */
    public function register() {
        Resque::redis()->sAdd(Key::localPoolProcesses($this->poolName), $this->getId());
    }

    /**
     * @param float $startTime
     * @param string $jobName
     * @param string $uniqueId
     *
     * @throws \Resque\RedisError
     */
    public function setRuntimeInfo($startTime, $jobName, $uniqueId) {
        Resque::redis()->set(
            Key::workerRuntimeInfo($this->getId()),
            (new RuntimeInfo($startTime, $jobName, $uniqueId))->toString()
        );
    }

    /**
     * @throws \Resque\RedisError
     */
    public function unregister() {
        Resque::redis()->sRem(Key::localPoolProcesses($this->poolName), $this->getId());
    }
}