<?php

namespace Resque\Worker;

class RuntimeInfo {

    const JOB_NAME = 'job_name';
    const START_TIME = 'start_time';
    const UNIQUE_ID = 'unique_id';

    /** @var float */
    public $startTime;
    /** @var string */
    public $jobName;
    /** @var string */
    public $uniqueId;

    /**
     * @param float $startTime
     * @param string $jobName
     * @param string $uniqueId
     */
    public function __construct($startTime = null, $jobName = null, $uniqueId = null) {
        $this->startTime = $startTime;
        $this->jobName = $jobName;
        $this->uniqueId = $uniqueId;
    }

    public static function fromString($string) {
        $rawInfo = json_decode($string, true);
        if (!\is_array($rawInfo)) {
            $rawInfo = [];
        }

        $info = new RuntimeInfo();
        $info->startTime = $rawInfo[self::START_TIME] ?? 0.0;
        $info->jobName = $rawInfo[self::JOB_NAME] ?? 'unset';
        $info->uniqueId = $rawInfo[self::UNIQUE_ID] ?? null;

        return $info;
    }

    public function toString() {
        return json_encode([
            self::START_TIME => $this->startTime,
            self::JOB_NAME => $this->jobName,
            self::UNIQUE_ID => $this->uniqueId
        ]);
    }
}