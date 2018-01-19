<?php

namespace Resque\Maintenance;

use Resque\Process\ProcessImage;

interface ProcessMaintainer {

    /**
     * @return ProcessImage[]
     */
    public function getLocalProcesses();

    /**
     * Cleans up and recovers local processes.
     *
     * @return void
     */
    public function maintain();
}