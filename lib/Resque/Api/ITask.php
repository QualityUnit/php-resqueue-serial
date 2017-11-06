<?php


namespace Resque\Api;


/**
 * @property Job job
 */
interface ITask {

    function perform();
}