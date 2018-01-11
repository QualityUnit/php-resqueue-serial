<?php

namespace Resque\StatsD;

/**
 * the statsd client
 */
class Client extends AbstractClient {

    /** @var Connection */
    private $connection;

    /**
     * @param Connection $connection
     * @param string $namespace global key namespace
     */
    public function __construct(Connection $connection, $namespace = '') {
        parent::__construct($namespace);
        $this->connection = $connection;
    }

    public function batch() {
        return new BatchClient($this->connection, $this->namespace);
    }

    protected function sendRawData($dataToSend) {
        $this->connection->send($dataToSend);
    }
}
