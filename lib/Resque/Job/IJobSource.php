<?php


namespace Resque\Job;


use Resque\Job\Reservations\JobUnavailableException;
use Resque\Stats;

interface IJobSource {

    /**
     * @return Stats
     */
    function getStats();

    /**
     * @param int $timeout maximum wait time in seconds
     *
     * @return QueuedJob|null Next job or null if source is empty
     * @throws JobUnavailableException when source can no longer provide jobs
     */
    function popBlocking($timeout);

    /**
     * @return QueuedJob|null Next job or null if source is empty
     * @throws JobUnavailableException when source can no longer provide jobs
     */
    function popNonBlocking();

    /**
     * String representation of the source.
     *
     * @return string
     */
    function toString();
}