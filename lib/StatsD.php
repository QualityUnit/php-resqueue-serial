<?php

namespace Resque;

use Qu\Statsd\Client;
use Qu\Statsd\Connection\MultiConnection;
use Qu\Statsd\Connection\UdpSocket;
use Resque\Config\StatsConfig;

class StatsD {

    /** @var Client */
    private static $client;

    public static function client() {
        if (self::$client === null) {
            self::$client = new Client(new MultiConnection()); // dummy
        }

        return self::$client;
    }

    public static function initialize(StatsConfig $config) {
        $connection = new MultiConnection();
        foreach ($config->getConnections() as $key => $connectionInfo) {
            $connection->addConnection(
                new UdpSocket(
                    $connectionInfo->getHost(),
                    $connectionInfo->getPort(),
                    $connectionInfo->getConnectTimeout()
                )
            );
        }

        self::$client = new Client($connection, Resque::VERSION_PREFIX);
    }
}