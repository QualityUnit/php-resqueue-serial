<?php

namespace Resque\Worker;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Process\AbstractProcessImage;

class WorkerImage extends AbstractProcessImage {

    /** @var string */
    private $poolName;
    /** @var string */
    private $code;

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

    protected function __construct($workerId, $nodeId, $poolName, $code, $pid) {
        parent::__construct($workerId, $nodeId, $pid);

        $this->poolName = $poolName;
        $this->code = $code;
    }

    public function clearState() {
        Resque::redis()->del(Key::worker($this->getId()));
    }

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    /**
     * @param string $stateData
     * @throws Resque\Api\RedisError
     */
    public function updateState($stateData) {
        Resque::redis()->set(Key::worker($this->getId()), $stateData);
    }

    public function unregister() {
        Resque::redis()->sRem(Key::localPoolProcesses($this->poolName), $this->getId());
    }

    public function register() {
        Resque::redis()->sAdd(Key::localPoolProcesses($this->poolName), $this->getId());
    }
}