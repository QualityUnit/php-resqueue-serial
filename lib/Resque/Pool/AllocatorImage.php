<?php

namespace Resque\Pool;

use Resque\Process\BaseProcessImage;

class AllocatorImage extends BaseProcessImage {

    /** @var string */
    private $number;

    protected function __construct($processId, $hostname, $number, $pid) {
        parent::__construct($processId, $hostname, $pid);

        $this->number = $number;
    }

    /**
     * @param $number
     *
     * @return self
     */
    public static function create($number) {
        $hostname = gethostname();
        $pid = getmypid();
        $id = "$hostname~$number~$pid";

        return new self($id, $hostname, $number, $pid);
    }

    public static function load($processId) {
        list($hostname, $number, $pid) = explode('~', $processId, 3);

        return new self($processId, $hostname, $number, $pid);
    }

    /**
     * @return string
     */
    public function getNumber() {
        return $this->number;
    }
}