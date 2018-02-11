<?php

namespace Resque\Pool;

use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Process\AbstractProcessImage;
use Resque\Resque;

class AllocatorImage extends AbstractProcessImage {

    /** @var string */
    private $code;

    protected function __construct($processId, $nodeId, $code, $pid) {
        parent::__construct($processId, $nodeId, $pid);

        $this->code = $code;
    }

    /**
     * @param string $code
     *
     * @return self
     */
    public static function create($code) {
        $nodeId = GlobalConfig::getInstance()->getNodeId();
        $pid = getmypid();
        $id = "$nodeId~$code~$pid";

        return new self($id, $nodeId, $code, $pid);
    }

    public static function load($processId) {
        list($nodeId, $code, $pid) = explode('~', $processId, 4);

        return new self($processId, $nodeId, $code, $pid);
    }

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

    public function unregister() {
        Resque::redis()->sRem(Key::localAllocatorProcesses(), $this->getId());
    }

    public function register() {
        Resque::redis()->sAdd(Key::localAllocatorProcesses(), $this->getId());
    }
}