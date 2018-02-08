<?php

namespace Resque\Process;

interface ProcessImage {

    /**
     * @return string
     */
    public function getNodeId();

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

    public function unregister();

    public function register();
}