<?php

namespace Resque\Config;

class AllocatorConfig {

    private $jobCount = 0;
    private $batchCount = 0;

    /**
     * @param mixed[] $configSection
     */
    public function __construct($configSection) {
        $jobCount = $configSection['job-count'] ?? -1;
        if ($jobCount >= 0) {
            $this->jobCount = (int)$jobCount;
        }
        $batchCount = $configSection['batch-count'] ?? -1;
        if ($batchCount >= 0) {
            $this->batchCount = (int)$batchCount;
        }
    }

    /**
     * @return int
     */
    public function getBatchCount() {
        return $this->batchCount;
    }

    /**
     * @return int
     */
    public function getJobCount() {
        return $this->jobCount;
    }
}