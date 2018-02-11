<?php

namespace Resque\Config;

class StatsConfig {

    private $host = 'localhost';
    private $port = 8125;
    private $connectTimeout = 3;

    /**
     * @param mixed[] $configSection
     */
    public function __construct($configSection) {
        $host = $configSection['host'];
        if ($host != null) {
            $this->host = $host;
        }
        $port = $configSection['port'];
        if ($port != null) {
            $this->port = $port;
        }
        $connectTimeout = $configSection['connect_timeout'];
        if ($connectTimeout != null) {
            $this->connectTimeout = $connectTimeout;
        }
    }

    /**
     * @return int
     */
    public function getConnectTimeout() {
        return $this->connectTimeout;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort() {
        return $this->port;
    }
}