<?php

namespace Resque\Process;

interface ProcessImage {

    /**
     * @return string
     */
    public function getHostname();

    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getPid();

    /**
     * @return boolean
     */
    public function isAlive();

    /**
     * @return boolean
     */
    public function isLocal();
}