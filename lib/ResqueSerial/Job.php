<?php


namespace ResqueSerial;


abstract class Job {

    /**
     * @return mixed[]
     */
    abstract function getArgs();

    /**
     * @return  string
     */
    public function getClass() {
        return static::class;
    }

    /**
     * @return string
     */
    abstract function getSecondarySerialId();

    /**
     * @return string
     */
    abstract function getSerialId();

    // TODO getEstimatedWorkTime
}