<?php


namespace Resque\Job;


interface IJobSource {

    /**
     * @return QueuedJob|null next job or null if source is empty
     */
    public function bufferNextJob();

    /**
     * @return QueuedJob|null buffered job or null if buffer is empty
     */
    public function bufferPop();
}