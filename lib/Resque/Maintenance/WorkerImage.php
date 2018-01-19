<?php

namespace Resque\Maintenance;

use Resque\Process\BaseProcessImage;

class WorkerImage extends BaseProcessImage {

    /** @var string */
    private $poolName;
    /** @var string */
    private $number;

    /**
     * @param $pool
     *
     * @return self
     */
    public static function create($pool) {
        
    }

    public static function load($workerId) {
        list($hostname, $poolName, $number, $pid) = explode('~', $workerId, 4);

        return new self($workerId, $hostname, $poolName, $number, $pid);
    }

    public function __construct($workerId, $hostname, $poolName, $number, $pid) {
        parent::__construct($workerId, $hostname, $pid);

        $this->poolName = $poolName;
        $this->number = $number;
    }


}