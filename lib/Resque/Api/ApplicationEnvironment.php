<?php


namespace Resque\Api;


interface ApplicationEnvironment {

    /**
     * @return string[] associative array of environment variables and their values
     */
    public function getEnvironment();

    /**
     * @return string relative include path
     */
    public function getIncludePath();

    /**
     * @return string version string
     */
    public function getVersion();
}