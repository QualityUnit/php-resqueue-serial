<?php

namespace Resque;

use Resque\Config\ConnectionInfo;
use Resque\Config\StatsConfig;
use Resque\StatsD\Client;
use Resque\StatsD\Connection\MultiConnection;
use Resque\StatsD\Connection\UdpSocket;

class StatsD {

    /** @var Client */
    private static $client;
    /** @var Client */
    private static $oldClient;

    public static function client() {
        if (self::$client === null) {
            self::$client = new Client(new MultiConnection()); // dummy
        }

        return self::$client;
    }

    public static function initialize(StatsConfig $config) {
        $connection = new MultiConnection();
        foreach ($config->getConnections() as $key => $connectionInfo) {
            if ($key === 'old') {
                self::createOldClient($connectionInfo);
                continue;
            }
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

    public static function oldClient() {
        if (self::$oldClient === null) {
            self::$oldClient = new Client(new MultiConnection()); // dummy
        }

        return self::$oldClient;
    }

    private static function createOldClient(ConnectionInfo $connectionInfo) {
        $socket = new UdpSocket(
            $connectionInfo->getHost(),
            $connectionInfo->getPort(),
            $connectionInfo->getConnectTimeout()
        );

        self::$oldClient = new Client($socket, Resque::VERSION_PREFIX);
    }
}