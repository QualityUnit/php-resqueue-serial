<?php

namespace ResqueSerial\Task;

use Resque_Worker;
use ResqueSerial\ResqueJob;

/**
 * Interface Resque_JobInterface
 *
 * @property mixed[] args
 * @property string queue
 * @property ResqueJob job
 * @property Resque_Worker worker
 */
interface ITask {
    /**
     * @return bool
     */
    public function perform();
}
