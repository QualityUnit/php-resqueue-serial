<?php

namespace Resque\Config;

use Resque;
use Resque\Job\StaticJobSource;
use Resque\Worker\WorkerImage;

interface IPool {

    /**
     * @param WorkerImage $workerImage
     *
     * @return StaticJobSource
     */
    public function createJobSource(WorkerImage $workerImage);

    /**
     * @return string
     */
    public function getName();
}