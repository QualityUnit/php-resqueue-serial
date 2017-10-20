<?php

namespace Resque;

use Resque;

class UniqueList {

    const STATE_QUEUED = 'queued';
    const STATE_RUNNING = 'running';

    public static function add($uniqueId) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
                || Resque::redis()->setNx('unique_list:' . $uniqueId, 'queued');
    }

    public static function edit($uniqueId, $newState) {
        // 1 or 0 from native redis, true or false from phpredis
        return !$uniqueId
                || Resque::redis()->set('unique_list:' . $uniqueId, $newState, ['XX']);
    }

    public static function remove($uniqueId) {
        return !$uniqueId
                || Resque::redis()->del('unique_list:' . $uniqueId) === 1;
    }
}