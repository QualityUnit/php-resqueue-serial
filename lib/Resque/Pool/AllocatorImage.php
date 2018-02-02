<?php

namespace Resque\Pool;

use Resque\Process\BaseProcessImage;

class AllocatorImage extends BaseProcessImage {

    /** @var string */
    private $code;

    protected function __construct($processId, $hostname, $code, $pid) {
        parent::__construct($processId, $hostname, $pid);

        $this->code = $code;
    }

    /**
     * @param string $code
     *
     * @return self
     */
    public static function create($code) {
        $hostname = gethostname();
        $pid = getmypid();
        $id = "$hostname~$code~$pid";

        return new self($id, $hostname, $code, $pid);
    }

    public static function load($processId) {
        list($hostname, $code, $pid) = explode('~', $processId, 4);

        return new self($processId, $hostname, $code, $pid);
    }

    /**
     * @return string
     */
    public function getCode() {
        return $this->code;
    }
}