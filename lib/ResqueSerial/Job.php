<?php


namespace ResqueSerial;


abstract class Job {

    /**
     * @return mixed[]
     */
    abstract function getArgs();

    /**
     * @return string
     */
    abstract function getClass();

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