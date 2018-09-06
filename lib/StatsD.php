<?php

namespace Resque;

use Resque\Config\StatsConfig;
use Resque\StatsD\Client;
use Resque\StatsD\Connection\MultiConnection;
use Resque\StatsD\Connection\UdpSocket;

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