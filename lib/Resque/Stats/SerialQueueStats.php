<?php


namespace Resque\Stats;


use Resque\Queue\SerialQueueImage;

class SerialQueueStats extends QueueStats {

    public function __construct(SerialQueueImage $queueImage) {
        parent::__construct($queueImage->getParentQueue());
    }
}