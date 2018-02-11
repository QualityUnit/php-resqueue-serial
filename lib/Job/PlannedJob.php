<?php

namespace Resque\Job;

use Resque\Protocol\Job;

class PlannedJob {

    const DATE_INTERVAL_FORMAT = 'P%yY%mM%dDT%hH%iM%sS';

    /** @var string */
    private $id;
    /** @var \DateTime */
    private $nextRun;
    /** @var \DateInterval */
    private $recurrenceInterval;
    /** @var Job */
    private $job;

    public static function fromArray(array $array) {
        $job = Job::fromArray($array);

        $nextRun = new \DateTime('@' . $array['nextRun']);
        $recurrenceInterval = new \DateInterval($array['recurrenceInterval']);

        return new PlannedJob($array['id'], $nextRun, $recurrenceInterval, $job);
    }

    public function __construct($id, \DateTime $nextRun, \DateInterval $recurrenceInterval, Job $job) {
        $this->nextRun = clone $nextRun;
        $this->recurrenceInterval = $recurrenceInterval;
        $this->job = $job;
        $this->id = $id;
    }

    /**
     * @return PlannedJob
     */
    public function copy() {
        return self::fromArray($this->toArray());
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    public function getJob() {
        return $this->job;
    }

    /**
     * @return int
     */
    public function getNextRunTimestamp() {
        return $this->nextRun->getTimestamp();
    }

    public function moveAfter($timestamp) {

        while($this->nextRun->getTimestamp() <= $timestamp) {
            $this->nextRun->add($this->recurrenceInterval);
        }
    }

    public function toArray() {
        $array = $this->job->toArray();
        $array['id'] = $this->id;
        $array['nextRun'] = $this->nextRun->getTimestamp();
        $array['recurrenceInterval'] = $this->recurrenceInterval->format(self::DATE_INTERVAL_FORMAT);

        return $array;
    }

    public function toString() {
        return json_encode($this->toArray());
    }
}