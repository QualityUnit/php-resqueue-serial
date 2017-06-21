<?php


namespace Resque\Worker\Serial\State;


/**
 * Interface IWorker represents worker capable of processing jobs from one or more queues.
 */
interface ISerialWorkerState {

    public function work();

    public function shutdown();
}