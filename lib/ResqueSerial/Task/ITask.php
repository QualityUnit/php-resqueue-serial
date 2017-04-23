<?php

namespace ResqueSerial\Task;

use ResqueSerial\DeprecatedWorker;
use ResqueSerial\ResqueJob;

/**
 * Interface Resque_JobInterface
 *
 * @property mixed[] args
 * @property string queue
 * @property ResqueJob job
 * @property DeprecatedWorker worker
 */
interface ITask {
    /**
     * @return bool
     */
    public function perform();
}
