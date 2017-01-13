<?php


namespace ResqueSerial;


use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use ResqueSerial\Init\GlobalConfig;

class Log {

    const LINE_FORMAT = "[%datetime%] %channel%.%level_name%: %message%\n";

    /**
     * @var LoggerInterface
     */
    private static $main = null;

    public static function initFromConfig(GlobalConfig $config) {
        $logger = new Logger('main');
        $handler = new StreamHandler($config->getLogPath(), $config->getLogLevel());
        $handler->setFormatter(new LineFormatter(self::LINE_FORMAT));
        $logger->pushHandler($handler);
        $logger->pushProcessor(new PsrLogMessageProcessor());
        self::setMain($logger);
    }

    /**
     * @return LoggerInterface
     */
    public static function main() {
        if (self::$main === null) {
            self::$main = new Logger('default');
        }

        return self::$main;
    }

    public static function prefix($prefix) {
        return new PrefixLogger($prefix, self::main());
    }

    public static function setMain($logger) {
        self::$main = $logger;
    }
}

class PrefixLogger extends AbstractLogger {

    /** @var string */
    private $prefix;
    /** @var LoggerInterface */
    private $logger;

    /**
     * PrefixLogger constructor.
     *
     * @param string $prefix
     * @param LoggerInterface $logger
     */
    public function __construct($prefix, LoggerInterface $logger) {
        $this->prefix = $prefix;
        $this->logger = $logger;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return null
     */
    public function log($level, $message, array $context = array()) {
        return $this->logger->log($level, '[' . $this->prefix . '] ' . $message, $context);
    }
}