<?php


namespace Resque;


interface Stats {

    public function incDequeued();

    public function incFailed();

    public function incProcessed();

    public function incProcessingTime($byMilliseconds);

    public function incQueueTime($byMilliseconds);

    public function incRetried();
}