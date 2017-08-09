<?php

namespace Resque;

use Resque;

class UniqueList {

    public static function add($uniqueId, $jobId = null) {
        return
            !$uniqueId
            || Resque::redis()->setNx('unique_list:' . $uniqueId, $jobId); // 1 or 0 from native redis, true or false from phpredis
    }

    public static function remove($uniqueId) {
        return
            !$uniqueId
            || Resque::redis()->del('unique_list:' . $uniqueId) === 1;
    }
}