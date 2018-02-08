<?php

namespace Resque\StatsD;

/**
 * the statsd client
 */
class BatchClient extends AbstractClient {

    /** @var IConnection */
    private $connection;

    /** @var string[] */
    private $batch = [];

    /**
     * @param IConnection $connection
     * @param string $namespace global key namespace
     */
    public function __construct(IConnection $connection, $namespace = '') {
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
