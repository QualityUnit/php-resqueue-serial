<?php


namespace Resque\Job\Processor;
use Resque\Exception;


/**
 * Thrown if the task class could not be found or is not according specification.
 */
class TaskCreationException extends Exception {
}