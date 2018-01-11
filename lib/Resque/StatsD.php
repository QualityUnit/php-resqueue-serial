<?php

namespace Resque;

use Resque\Config\StatsConfig;
use Resque\StatsD\BatchClient;
use Resque\StatsD\Client;
use Resque\StatsD\Connection\UdpSocket;

class StatsD {

    /** @var Client */
    private static $client;

    /**
     * @return BatchClient
     */
    public static function batch() {
        return self::$client->batch();
    }

    /**
     * @param string $key
     * @param int $value
     * @param int $sampleRate (optional) the default is 1
     * @param array $tags
     */
    public static function count($key, $value, $sampleRate = 1, array $tags = []) {
        self::$client->count($key, $value, $sampleRate, $tags);
    }

    /**
     * @param string $key
     * @param int $sampleRate
     * @param array $tags
     */
    public static function decrement($key, $sampleRate = 1, array $tags = []) {
        self::$client->decrement($key, $sampleRate, $tags);
    }

    /**
     * @param string $key
     * @param string|int $value
     * @param array $tags
     */
    public static function gauge($key, $value, array $tags = []) {
        self::$client->gauge($key, $value, $tags);
    }

    /**
     * @param string $key
     * @param int $sampleRate
     * @param array $tags
     */
    public static function increment($key, $sampleRate = 1, array $tags = []) {
        self::$client->increment($key, $sampleRate, $tags);
    }

    public static function initialize(StatsConfig $config) {
        $connection = new UdpSocket(
            $config->getHost(),
            $config->getPort(),
            $config->getRetryPeriod(),
            $config->getConnectTimeout()
        );

        self::$client = new Client($connection, \Resque::VERSION_PREFIX);
    }

    /**
     * @param string $key
     * @param int $value
     * @param array $tags
     */
    public static function set($key, $value, array $tags = []) {
        self::$client->set($key, $value, $tags);
    }

    /**
     * @param string $key
     * @param int $value the timing in ms
     * @param int $sampleRate the sample rate, if < 1, statsd will send an average timing
     * @param array $tags
     */
    public static function timing($key, $value, $sampleRate = 1, array $tags = []) {
        self::$client->timing($key, $value, $sampleRate, $tags);
    }
}