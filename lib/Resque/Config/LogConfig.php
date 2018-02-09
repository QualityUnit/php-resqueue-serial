<?php

namespace Resque\Config;

use Psr\Log\LogLevel;
use Resque\Log;

class LogConfig {

    const LOG_LEVELS = [
        'ALERT' => Log::ALERT,
        'CRITICAL' => Log::CRITICAL,
        'DEBUG' => Log::DEBUG,
        'EMERGENCY' => Log::EMERGENCY,
        'ERROR' => Log::ERROR,
        'INFO' => Log::INFO,
        'NOTICE' => Log::NOTICE,
        'WARNING' => Log::WARNING
    ];

    private $level = LogLevel::NOTICE;
    private $path = '/var/log/resque-serial.log';
    private $applicationName = \Resque\Resque::VERSION_PREFIX;
    private $systemName;
    private $extraPrefix = '';
    private $contextPrefix = 'ctxt_';
    private $version = 0;

    /**
     * @param mixed[] $configSection
     */
    public function __construct($configSection) {

        $level = self::LOG_LEVELS[strtoupper($configSection['level'])];
        if ($level != null) {
            $this->level = $level;
        }
        $logPath = $configSection['path'];
        if ($logPath != null) {
            $this->path = $logPath;
        }
        $applicationName = $configSection['application_name'];
        if ($applicationName != null) {
            $this->applicationName = $applicationName;
        }
        $systemName = $configSection['system_name'];
        if ($systemName != null) {
            $this->systemName = $systemName;
        } else {
            $this->systemName = GlobalConfig::getInstance()->getNodeId();
        }
        $extraPrefix = $configSection['extra_prefix'];
        if ($extraPrefix != null) {
            $this->extraPrefix = $extraPrefix;
        }
        $contextPrefix = $configSection['context_prefix'];
        if ($contextPrefix != null) {
            $this->contextPrefix = $contextPrefix;
        }
        $version = $configSection['logstash_version'];
        if ($version != null) {
            $this->version = $version;
        }
    }

    public static function forPath($path, $level) {
        $logger = new self([]);
        $logger->path = $path;
        $logger->level = $level;

        return $logger;
    }

    /**
     * @return string
     */
    public function getApplicationName() {
        return $this->applicationName;
    }

    /**
     * @return string
     */
    public function getContextPrefix() {
        return $this->contextPrefix;
    }

    /**
     * @return string
     */
    public function getExtraPrefix() {
        return $this->extraPrefix;
    }

    /**
     * @return mixed|string
     */
    public function getLevel() {
        return $this->level;
    }

    /**
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * @return mixed
     */
    public function getSystemName() {
        return $this->systemName;
    }

    /**
     * @return int
     */
    public function getVersion() {
        return $this->version;
    }


}