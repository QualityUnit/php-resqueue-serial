<?php


namespace Resque\Job;


use Resque\Job\Reservations\JobUnavailableException;

interface IJobSource {

    /**
     * @return QueuedJob|null Next job or null if source is empty
     * @throws JobUnavailableException when source can no longer provide jobs
     */
    public function getNextJob();

}