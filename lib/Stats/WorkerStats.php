<?php

namespace Resque\Stats;

use Resque\Worker\WorkerImage;

class WorkerStats extends AbstractStats {

    private static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self('workers');
        }

        return self::$instance;
    }

    /**
     * @param WorkerImage $image
     * @param int $runtime
     */
    public function reportJobRuntime(WorkerImage $image, $runtime) {
        $this->gauge("{$image->getPoolName()}.{$image->getCode()}.{$image->getPid()}.runtime", (int) $runtime);
    }

}