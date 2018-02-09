<?php

namespace Resque\Pool;

use Resque\Key;

class BatchImage {

    private $id;
    private $sourceId;
    private $jobName;
    private $suffix;

    /**
     * @param $id
     * @param $sourceId
     * @param $jobName
     * @param $suffix
     */
    public function __construct($id, $sourceId, $jobName, $suffix) {
        $this->id = $id;
        $this->sourceId = $sourceId;
        $this->jobName = $jobName;
        $this->suffix = $suffix;
    }

    public static function load($batchId) {
        list($sourceId, $jobName, $suffix) = explode(':', $batchId, 3);

        return new self($batchId, $sourceId, $jobName, $suffix);
    }

    public function getKey() {
        return Key::committedBatch($this->id);
    }

    /**
     * @return mixed
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getJobName() {
        return $this->jobName;
    }

    /**
     * @return mixed
     */
    public function getSourceId() {
        return $this->sourceId;
    }

    /**
     * @return mixed
     */
    public function getSuffix() {
        return $this->suffix;
    }
}