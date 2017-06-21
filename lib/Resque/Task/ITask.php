<?php


namespace Resque\Task;
use Resque\Job\Job;


/**
 * @property Job job
 */
interface ITask {

    function perform();
}