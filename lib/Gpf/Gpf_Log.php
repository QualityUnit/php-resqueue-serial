<?php

class Gpf_Log  {
    const CRITICAL = 50;
    const DEBUG = 10;
    const ERROR = 40;
    const INFO = 20;
    const WARNING = 30;
    /** @var mixed[][] */
    private static $records = [];

    private static $initialTime;

    public static function addLogger($type, $logLevel) {
        return $type;
    }

    /**
     * logs critical error message
     *
     * @param string $message
     * @param string $logGroup
     */
    public static function critical($message, $logGroup = null) {
        self::log($message, self::CRITICAL, $logGroup);
    }

    /**
     * logs debug message
     *
     * @param string $message
     * @param string $logGroup
     */
    public static function debug($message, $logGroup = null) {
        self::log($message, self::DEBUG, $logGroup);
    }

    public static function disableType($type) {
    }

    public static function dump() {
        $now = time();
        if ($now - self::$initialTime > 1800) {
            foreach (self::$records as $record) {
                \Resque\Log::notice(date('Y-m-d-H-i-s', $record['time']) . ' ' . $record['message'], [
                    'level' => $record['level']
                ]);
            }
        }
        self::$records = [];
    }

    public static function enableAllTypes() {
    }

    /**
     * logs error message
     *
     * @param string $message
     * @param string $logGroup
     */
    public static function error($message, $logGroup = null) {
        self::log($message, self::ERROR, $logGroup);
    }

    /**
     * @param $logLevel
     *
     * @return string
     */
    public static function getLevelAsText($logLevel) {
        switch($logLevel) {
            case self::CRITICAL: return 'Critical';
            case self::ERROR:    return 'Error';
            case self::WARNING:  return 'Warning';
            case self::INFO:     return 'Info';
            case self::DEBUG:    return 'Debug';
        }

        return ' Unknown';
    }

    /**
     * logs info message
     *
     * @param string $message
     * @param string $logGroup
     */
    public static function info($message, $logGroup = null) {
        self::log($message, self::INFO, $logGroup);
    }

    public static function init() {
        self::$initialTime = time();
        self::$records = [];
    }

    public static function isLogToDisplay() {
    }

    /**
     * logs message
     *
     * @param string $message
     * @param string $logLevel
     * @param string $logGroup
     */
    public static function log($message, $logLevel, $logGroup = null) {
        self::$records[] = [
            'level' => $logLevel,
            'time' => time(),
            'message' => $message
        ];
    }

    public static function removeAll() {
    }

    /**
     * logs warning message
     *
     * @param string $message
     * @param string $logGroup
     */
    public static function warning($message, $logGroup = null) {
        self::log($message, self::WARNING, $logGroup);
    }
}