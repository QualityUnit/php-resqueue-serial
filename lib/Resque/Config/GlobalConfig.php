<?php


namespace Resque\Config;


use Resque\Exception;
use Resque\Redis;
use Symfony\Component\Yaml\Yaml;

class GlobalConfig {

    /** @var GlobalConfig */
    private static $instance;

    private $queues = [];
    private $logConfig;
    private $redisHost = Redis::DEFAULT_HOST;
    private $redisPort = Redis::DEFAULT_PORT;

    private $taskIncludePath = '/opt';
    private $path;

    /** @var int */
    private $maxTaskFails = 3;

    private function __construct($path) {
        $this->path = $path;
    }

    /**
     * @param $path
     * @return GlobalConfig
     * @throws Exception
     */
    public static function initialize($path) {
        self::$instance = new self($path);
        self::reload();

        return self::$instance;
    }

    /**
     * @return GlobalConfig
     * @throws Exception
     */
    public static function getInstance() {
        if (!self::$instance) {
            throw new Exception('No instance of GlobalConfig exist');
        }
        return self::$instance;
    }

    /**
     * @throws Exception
     */
    public static function reload() {
        $self = self::$instance;
        $data = Yaml::parse(file_get_contents($self->path));

        if (!isset($data['queues'])) {
            throw new Exception('Global config contains no queues.');
        }
        $self->queues = $data['queues'];

        $redis = $data['redis'];
        if (is_array($redis)) {
            $self->redisHost = $redis['hostname'];
            $self->redisPort = $redis['port'];
        }

        $self->logConfig = new LogConfig($data['log']);

        $taskIncludePath = $data['task_include_path'];
        if ($taskIncludePath != null) {
            $self->taskIncludePath = $taskIncludePath;
        }
        $failRetries = $data['fail_retries'];
        if ($failRetries != null) {
            $self->maxTaskFails = (int)$failRetries;
        }
    }

    /**
     * @return string
     */
    public function getBackend() {
        return $this->redisHost . ':' . $this->redisPort;
    }

    public function getLogConfig() {
        return $this->logConfig;
    }

    /**
     * @return string
     */
    public function getLogLevel() {
        return $this->logLevel;
    }

    /**
     * @return string
     */
    public function getLogPath() {
        return $this->logPath;
    }

    /**
     * @return int
     */
    public function getMaxTaskFails() {
        return $this->maxTaskFails;
    }

    /**
     * @return string[]
     */
    public function getQueueList() {
        return array_keys($this->queues);
    }

    /**
     * @return string
     */
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

}