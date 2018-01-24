<?php

namespace Resque\Config;

class PoolConfig {

    /** @var string */
    private $name;
    /** @var int */
    private $unitCount;
    /** @var int */
    private $workersPerUnit;
    /** @var string[] */
    private $sources;
    /** @var string[] */
    private $jobNames;

    /**
     * @param mixed[] $poolSection
     */
    public function __construct($poolSection) {
        $this->name = $poolSection['name'];
        $this->unitCount = $poolSection['unit_count'];
        $this->workersPerUnit = $poolSection['workers_per_unit'];

        $this->sources = [];
        if(isset($poolSection['sources']) && \is_array($poolSection['sources'])) {
           foreach ($poolSection['sources'] as $source) {
               $this->sources[] = $source;
           }
        }

        $this->jobNames = [];
        if(isset($poolSection['job_names']) && \is_array($poolSection['job_names'])) {
            foreach ($poolSection['job_names'] as $jobName) {
                $this->jobNames[] = $jobName;
            }
        }
    }

}