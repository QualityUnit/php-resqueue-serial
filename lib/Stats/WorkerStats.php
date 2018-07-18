<?php

namespace Resque\Stats;

use Resque\SingletonTrait;
use Resque\Worker\WorkerImage;

class WorkerStats {

    use SingletonTrait;

    public function reportJobRuntime(WorkerImage $image, int $runtime) {
        Stats::old()->gauge("workers.{$image->getPoolName()}.{$image->getCode()}.{$image->getPid()}.runtime", $runtime);

        Stats::node()->gauge("worker.{$image->getPoolName()}-{$image->getCode()}-{$image->getPid()}.runtime", $runtime);
    }

}