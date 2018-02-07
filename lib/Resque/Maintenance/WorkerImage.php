<?php

namespace Resque\Maintenance;

use Resque\Process\BaseProcessImage;

class WorkerImage extends BaseProcessImage {

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

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }

}