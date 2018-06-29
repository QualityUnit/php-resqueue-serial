<?php

namespace Resque\Config;

class ConnectionInfo {

    private $host = 'localhost';
    private $port = 8125;
    private $connectTimeout = 3;
    private $isDefault = true;

    /**
     * @param mixed[] $configSection
     */
    public function __construct($configSection) {
        if (!\is_array($configSection)) {
            return;
        }
        $host = $configSection['host'] ?? null;
        if ($host != null) {
            $this->host = $host;
            $this->isDefault = false;
        }
        $port = $configSection['port'] ?? null;
        if ($port != null) {
            $this->port = $port;
            $this->isDefault = false;
        }
        $connectTimeout = $configSection['connect_timeout'] ?? null;
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

    /**
     * @return bool
     */
    public function isDefault() {
        return $this->isDefault;
    }
}