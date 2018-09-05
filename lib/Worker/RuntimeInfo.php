<?php

namespace Resque\Worker;

class RuntimeInfo {

    /** @var float */
    public $startTime;
    /** @var string */
    public $jobName;
    /** @var string */
    public $uniqueId;
}