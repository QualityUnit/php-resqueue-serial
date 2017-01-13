<?php


namespace ResqueSerial\Serial;


use Resque_Job;
use ResqueSerial\Key;
use ResqueSerial\Log;

class SerialQueue extends \Resque_Queue {

    public function blockingPop($timeout = null) {
        $queue = $this->getQueues()[0];
        $item = \Resque::redis()->blpop(Key::serialQueue($queue), (int)$timeout);

        if(!$item) {
            return false;
        }

        return new Resque_Job($queue, json_decode($item[1], true));
    }

    public function pop() {
        $queue = $this->getQueues()[0];

        // todo pass instance logger
        $size = \Resque::redis()->llen(Key::serialQueue($queue));;
        Log::main()->debug("Pop from Queue: $queue, Size: $size");

        $item = \Resque::redis()->lpop(Key::serialQueue($queue));


        if(!$item) {
            return false;
        }

        return new Resque_Job($queue, json_decode($item, true));
    }
}