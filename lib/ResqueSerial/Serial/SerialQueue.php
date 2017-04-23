<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\ResqueJob;

class SerialQueue extends \Resque_Queue {

    public function blockingPop($timeout = null) {
        $queue = $this->getQueues()[0];
        $item = \Resque::redis()->blpop(Key::serialQueue($queue), (int)$timeout);

        if(!$item) {
            return false;
        }

        return new ResqueJob($queue, json_decode($item[1], true));
    }

    public function pop() {
        $queue = $this->getQueues()[0];

        $size = \Resque::redis()->llen(Key::serialQueue($queue));;
        Log::local()->debug("Pop from Queue: $queue, Size: $size");

        $item = \Resque::redis()->lpop(Key::serialQueue($queue));


        if(!$item) {
            return false;
        }

        return new ResqueJob($queue, json_decode($item, true));
    }
}