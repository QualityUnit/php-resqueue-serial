<?php

namespace Resque\StatsD;

/**
 * the statsd client
 */
class BatchClient extends AbstractClient {

    /** @var Connection */
    private $connection;

    /** @var string[] */
    private $batch = [];

    /**
     * @param Connection $connection
     * @param string $namespace global key namespace
     */
    public function __construct(Connection $connection, $namespace = '') {
        parent::__construct($namespace);
        $this->connection = $connection;
    }

    /**
     * Send all stats since last commit
     */
    public function commit() {
        $this->connection->sendMessages($this->batch);
        $this->batch = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function sendRawData($dataToSend) {
        $this->batch[] = $dataToSend;
    }
}
