<?php


namespace Resque\Process;


use Resque;
use Resque\Config\GlobalConfig;
use Resque\Key;

class SchedulerImage extends AbstractProcessImage {

    /**
     * @return self
     */
    public static function create() {
        $nodeId = GlobalConfig::getInstance()->getNodeId();
        $pid = getmypid();

        return new self("$nodeId~$pid", $nodeId, $pid);
    }

    /**
     * @param string $processId
     *
     * @return self
     */
    public static function load($processId) {
        list($nodeId, $pid) = explode('~', $processId, 2);

        return new self($processId, $nodeId, $pid);
    }

    public function register() {
        Resque::redis()->sAdd(Key::localSchedulerProcesses(), $this->getId());
    }

    public function unregister() {
        Resque::redis()->sRem(Key::localSchedulerProcesses(), $this->getId());
    }
}