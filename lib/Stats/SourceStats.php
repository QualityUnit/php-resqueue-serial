<?php

namespace Resque\Stats;

use Resque\Job\RunningJob;

class SourceStats extends AbstractStats {

    private static $instance;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self('sources');
        }

        return self::$instance;
    }

    public function reportJobProcessing(RunningJob $job) {
        $this->inc("{$job->getJob()->getSourceId()}.{$job->getName()}", 1);
    }
}