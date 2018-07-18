<?php

namespace Resque\Stats;

use Resque\Job\RunningJob;
use Resque\SingletonTrait;

class SourceStats {

    use SingletonTrait;

    public function reportJobProcessing(RunningJob $job) {
        Stats::old()->increment("sources.{$job->getJob()->getSourceId()}.{$job->getName()}");
    }
}