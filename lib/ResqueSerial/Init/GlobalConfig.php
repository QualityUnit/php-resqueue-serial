<?php


namespace ResqueSerial\Init;


use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Yaml;

class GlobalConfig {

    const LOG_LEVELS = [
            'ALERT' => LogLevel::ALERT,
            'CRITICAL' => LogLevel::CRITICAL,
            'DEBUG' => LogLevel::DEBUG,
            'EMERGENCY' => LogLevel::EMERGENCY,
            'ERROR' => LogLevel::ERROR,
            'INFO' => LogLevel::INFO,
            'NOTICE' => LogLevel::NOTICE,
            'WARNING' => LogLevel::WARNING
    ];

    public static $PATH = '/etc/resque-serial/config.yml';

    /** @var GlobalConfig */
    private static $instance;

    private $queues = [];
    private $logLevel = LogLevel::NOTICE;
    private $logPath = '/var/log/resque-serial.log';
    private $redisHost = \Resque_Redis::DEFAULT_HOST;
    private $redisPort = \Resque_Redis::DEFAULT_PORT;

    private $taskIncludePath = '/opt';
    private $path;

    public function __construct($path) {
        $this->path = $path;
        $data = Yaml::parse(file_get_contents($path));

        if (!isset($data['queues'])) {
            throw new \Exception("Global config contains no queues.");
        }

        $this->queues = $data['queues'];

        $redis = $data['redis'];
        if (is_array($redis)) {
            $this->redisHost = $redis['hostname'];
            $this->redisPort = $redis['port'];
        }

        $level = self::LOG_LEVELS[strtoupper($data['log_level'])];
        if ($level != null) {
            $this->logLevel = $level;
        }
        $logPath = $data['log_path'];
        if ($logPath != null) {
            $this->logPath = $logPath;
        }
        $taskIncludePath = $data['task_include_path'];
        if ($taskIncludePath != null) {
            $this->taskIncludePath = $taskIncludePath;
        }
    }

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new GlobalConfig(self::$PATH);
        }
        return self::$instance;
    }

    public static function reload() {
        self::$instance = new GlobalConfig(self::$instance->getPath());
    }

    public function getBackend() {
        return $this->redisHost . ':' . $this->redisPort;
    }

    public function getLogLevel() {
        return $this->logLevel;
    }

    /**
     * @return string
     */
    public function getLogPath() {
        return $this->logPath;
    }

    public function getQueueList() {
        return array_keys($this->queues);
    }

    public function getTaskIncludePath() {
        return $this->taskIncludePath;
    }

    /**
     * @param $queue
     *
     * @return WorkerConfig|null
     */
    public function getWorkerConfig($queue) {
        try {
            $queueData = $this->queues[$queue];

            return new WorkerConfig($queueData);
        } catch (\Exception $ignore) {
        }

        return null;
    }

    private function getPath() {
        return $this->path;
    }
}