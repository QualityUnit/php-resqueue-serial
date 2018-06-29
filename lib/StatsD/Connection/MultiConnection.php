<?php

namespace Resque\StatsD\Connection;

use Resque\StatsD\IConnection;

class MultiConnection implements IConnection {

    /** @var IConnection[] */
    private $connections = [];

    public function addConnection(IConnection $connection) {
        $this->connections[] = $connection;
    }

    /**
     * sends a message to statsd
     *
     * @param string $message
     */
    public function send($message) {
        foreach ($this->connections as $connection) {
            $connection->send($message);
        }
    }

    /**
     * sends multiple messages to statsd
     *
     * @param array $messages
     */
    public function sendMessages(array $messages) {
        foreach ($this->connections as $connection) {
            $connection->sendMessages($messages);
        }
    }
}