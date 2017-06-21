<?php


namespace Resque;


use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Resque\Config\GlobalConfig;

class Log {

    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const DEBUG = 'debug';
    const EMERGENCY = 'emergency';
    const ERROR = 'error';
    const INFO = 'info';
    const NOTICE = 'notice';
    const WARNING = 'warning';

    const LINE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context.exception%\n";

    /** @var Log */
    private static $instance;

    /** @var \Monolog\Logger */
    private $logger;

    private function __construct() {
        $this->logger = new Logger('default');
    }

    public static function alert($message, array $context = []) {
        self::getInstance()->logger->log(self::ALERT, $message, $context);
    }

    public static function critical($message, array $context = []) {
        self::getInstance()->logger->log(self::CRITICAL, $message, $context);
    }

    public static function debug($message, array $context = []) {
        self::getInstance()->logger->log(self::DEBUG, $message, $context);
    }

    public static function emergency($message, array $context = []) {
        self::getInstance()->logger->log(self::EMERGENCY, $message, $context);
    }

    public static function error($message, array $context = []) {
        self::getInstance()->logger->log(self::ERROR, $message, $context);
    }

    public static function info($message, array $context = []) {
        self::getInstance()->logger->log(self::INFO, $message, $context);
    }

    public static function initialize(GlobalConfig $config) {
        $formatter = new LineFormatter(self::LINE_FORMAT);
        $formatter->includeStacktraces(true);

        $handler = new StreamHandler($config->getLogPath(), $config->getLogLevel());
        $handler->setFormatter($formatter);

        $logger = new Logger('main');
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $logger->pushHandler($handler);

        self::getInstance()->logger = $logger;
    }

    public static function notice($message, array $context = []) {
        self::getInstance()->logger->log(self::NOTICE, $message, $context);
    }

    public static function prefix($prefix) {
        return new PrefixLogger($prefix, self::getInstance()->logger);
    }

    public static function setLogger(LoggerInterface $logger) {
        self::getInstance()->logger = $logger;
    }

    public static function warning($message, array $context = []) {
        self::getInstance()->logger->log(self::WARNING, $message, $context);
    }

    private static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

class PrefixLogger extends AbstractLogger {

    /** @var string */
    private $prefix;
    /** @var LoggerInterface */
    private $logger;

    /**
     * PrefixLogger constructor.
     * @param string $prefix
     * @param LoggerInterface $logger
     */
    public function __construct($prefix, LoggerInterface $logger) {
        $this->prefix = $prefix;
        $this->logger = $logger;
    }

    /**
     * Logs with an arbitrary level.
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []) {
        $this->logger->log($level, '[' . $this->prefix . '] ' . $message, $context);
    }
}