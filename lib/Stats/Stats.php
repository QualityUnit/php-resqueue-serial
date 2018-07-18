<?php

namespace Resque\Stats;

use Resque\Config\GlobalConfig;
use Resque\StatsD;
use Resque\StatsD\Client;

class Stats {

    /** @var bool */
    private $isOld = false;
    /** @var string */
    private $nodeId;
    /** @var string */
    private $sourceId = '';


    private function __construct(string $nodeId) {
        $this->nodeId = $nodeId;
    }


    public static function global(): self {
        return new static('');
    }

    public static function node(): self {
        return new static(GlobalConfig::getInstance()->getNodeId());
    }

    public static function old(): self {
        $stats = self::node();
        $stats->isOld = true;

        return $stats;
    }

    /**
     * @param string $key
     * @param int $value
     * @param float $sampleRate
     * @param array $tags
     *
     * @return $this
     */
    public function count(string $key, int $value, float $sampleRate = 1, array $tags = []): self {
        $this->client()->count($this->prefix($key), $value, $sampleRate, $tags);

        return $this;
    }

    /**
     * @param string $key
     * @param float $sampleRate
     * @param array $tags
     *
     * @return Stats
     */
    public function decrement(string $key, float $sampleRate = 1, array $tags = []): self {
        return $this->count($key, -1, $sampleRate, $tags);
    }

    /**
     * @param string $sourceId
     *
     * @return $this
     */
    public function forSource(string $sourceId): self {
        $this->sourceId = $sourceId;

        return $this;
    }

    /**
     * @param string $key
     * @param int $value
     * @param array $tags
     *
     * @return $this
     */
    public function gauge(string $key, int $value, array $tags = []): self {
        $this->client()->gauge($this->prefix($key), $value, $tags);

        return $this;
    }

    /**
     * @param string $key
     * @param float $sampleRate
     * @param array $tags
     *
     * @return Stats
     */
    public function increment(string $key, float $sampleRate = 1, array $tags = []): self {
        return $this->count($key, 1, $sampleRate, $tags);
    }

    /**
     * @param string $key
     * @param int $value
     * @param array $tags
     *
     * @return $this
     */
    public function set(string $key, int $value, array $tags = []): self {
        $this->client()->set($key, $value, $tags);

        return $this;
    }

    /**
     * @param string $key
     * @param int $value
     * @param float $sampleRate
     * @param array $tags
     *
     * @return $this
     */
    public function timing(string $key, int $value, float $sampleRate = 1, array $tags = []): self {
        $this->client()->timing($key, $value, $sampleRate, $tags);

        return $this;
    }

    private function client(): Client {
        if ($this->isOld) {
            return StatsD::oldClient();
        }

        return StatsD::client();
    }

    private function prefix(string $key): string {
        return "{$this->nodeId}." . ($this->isOld ? '' : "{$this->sourceId}.") . $key;
    }
}