<?php


namespace Resque\Config;


use Resque\Log;
use Resque\Redis;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class GlobalConfig {

    /** @var GlobalConfig */
    private static $instance;

    /** @var LogConfig */
    private $logConfig;
    /** @var StatsConfig */
    private $statsConfig;
    /** @var MappingConfig */
    private $staticPoolMapping;
    /** @var MappingConfig */
    private $batchPoolMapping;
    /** @var BatchPoolConfig */
    private $batchPools;
    /** @var StaticPoolConfig */
    private $staticPools;
    /** @var AllocatorConfig */
    private $allocatorConfig;
    /** @var string */
    private $redisHost = Redis::DEFAULT_HOST;
    /** @var int */
    private $redisPort = Redis::DEFAULT_PORT;
    /** @var string */
    private $taskIncludePath = '/opt';
    /** @var int */
    private $maxTaskFails = 3;
    /** @var string */
    private $nodeId;

    /** @var string */
    private $configPath;

    /**
     * @param string $configPath
     */
    private function __construct($configPath) {
        $this->configPath = $configPath;
    }

    /**
     * @return GlobalConfig
     */
    public static function getInstance() {
        if (!self::$instance) {
            throw new \RuntimeException('No instance of GlobalConfig exist');
        }

        return self::$instance;
    }

    /**
     * @param $path
     *
     * @return GlobalConfig
     */
    public static function initialize($path) {
        self::$instance = new self($path);
        self::reload();

        return self::$instance;
    }

    public static function reload() {
        $self = self::$instance;
        try {
            /** @var mixed[] $data */
            $data = Yaml::parse(file_get_contents($self->configPath));
        } catch (ParseException $e) {
            Log::critical('Failed to load configuration.', [
                'exception' => $e
            ]);
            throw new \RuntimeException('Config file failed to parse.');
        }

        if (!isset($data['node_id']) || !self::isNodeIdValid($data['node_id'])) {
            throw new \RuntimeException('Config key node_id invalid or missing.');
        }

        if (!isset($data['pools'])) {
            throw new \RuntimeException('Pools config section missing.');
        }

        if (!isset($data['mapping'])) {
            throw new \RuntimeException('Mapping config section missing.');
        }

        $redis = $data['redis'];
        if (\is_array($redis)) {
            $self->redisHost = $redis['hostname'];
            $self->redisPort = $redis['port'];
        }
        $taskIncludePath = $data['task_include_path'];
        if ($taskIncludePath) {
            $self->taskIncludePath = $taskIncludePath;
        }
        $failRetries = $data['fail_retries'] ?? -1;
        if ($failRetries >= 0) {
            $self->maxTaskFails = (int)$failRetries;
        }
        $self->nodeId = $data['node_id'];

        $self->logConfig = new LogConfig($data['log']);
        $self->statsConfig = new StatsConfig($data['statsd']);
        $self->staticPoolMapping = new MappingConfig($data['mapping']['static']);
        $self->batchPoolMapping = new MappingConfig($data['mapping']['batch']);
        $self->staticPools = new StaticPoolConfig($data['pools']['static']);
        $self->batchPools = new BatchPoolConfig($data['pools']['batch']);
        $self->allocatorConfig = new AllocatorConfig($data['allocators']);
    }

    private static function isNodeIdValid($nodeId) {
        return preg_match('/^\w+$/', $nodeId) === 1;
    }

    /**
     * @return AllocatorConfig
     */
    public function getAllocatorConfig() {
        return $this->allocatorConfig;
    }

    /**
     * @return string
     */
    public function getBackend() {
        return $this->redisHost . ':' . $this->redisPort;
    }

    /**
     * @return BatchPoolConfig
     */
    public function getBatchPoolConfig() {
        return $this->batchPools;
    }

    /**
     * @return MappingConfig
     */
    public function getBatchPoolMapping() {
        return $this->batchPoolMapping;
    }

    /**
     * @return LogConfig
     */
    public function getLogConfig() {
        return $this->logConfig;
    }

    /**
     * @return int
     */
    public function getMaxTaskFails() {
        return $this->maxTaskFails;
    }

    /**
     * @return string
     */
    public function getNodeId() {
        return $this->nodeId;
    }

    /**
     * @deprecated
     * @return string[]
     */
    public function getQueueList() {
    }

    /**
     * @return StaticPoolConfig
     */
    public function getStaticPoolConfig() {
        return $this->staticPools;
    }

    /**
     * @return MappingConfig
     */
    public function getStaticPoolMapping() {
        return $this->staticPoolMapping;
    }

    /**
     * @return StatsConfig
     */
    public function getStatsConfig() {
        return $this->statsConfig;
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