<?php


namespace Resque\Job\Reservations;


use Resque\Job\IJobSource;
use Resque\Job\QueuedJob;

interface IStrategy {

    /**
     * @param IJobSource $source source of jobs to reserve from
     *
     * @return QueuedJob
     * @throws WaitException when there were no jobs in queue
     * @throws TerminateException when worker should end
     * @throws JobUnavailableException when source can no longer provide jobs
     */
    function reserve(IJobSource $source);

}