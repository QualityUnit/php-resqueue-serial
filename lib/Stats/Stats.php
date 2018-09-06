<?php

namespace Resque\Stats;

use Resque\Config\GlobalConfig;
use Resque\StatsD;

class Stats {

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

    /**
     * @param string $key
     * @param int $value
     * @param float $sampleRate
     * @param array $tags
     *
     * @return $this
     */
    public function count(string $key, int $value, float $sampleRate = 1, array $tags = []): self {
        StatsD::client()->count($this->prefix($key), $value, $sampleRate, $tags);

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
        StatsD::client()->gauge($this->prefix($key), $value, $tags);

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
        StatsD::client()->set($this->prefix($key), $value, $tags);

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
        StatsD::client()->timing($this->prefix($key), $value, $sampleRate, $tags);

        return $this;
    }

    private function prefix(string $key): string {
        return "{$this->nodeId}.{$this->sourceId}.$key";
    }
}