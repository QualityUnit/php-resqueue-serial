<?php


namespace Resque\Job;


interface IJobSource {

    /**
     * @return QueuedJob|null Next job or null if source is empty
     * @throws JobUnavailableException when source can no longer provide jobs
     */
    public function getNextJob();

}