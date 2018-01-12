<?php

namespace Resque\StatsD\Connection;

use Resque\StatsD\Connection;

abstract class Socket implements Connection {
    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var int|null */
    private $timeout;

    /**
     * Maximum Transmission Unit
     * http://en.wikipedia.org/wiki/Maximum_transmission_unit
     *
     * @var int
     */
    private $mtu;

    /**
     * instantiates the Connection object and a real connection to statsd
     *
     * @param string $host Statsd hostname
     * @param int $port Statsd port
     * @param int $timeout Connection timeout
     * @param int $mtu Maximum Transmission Unit (default: 1500)
     */
    public function __construct($host = 'localhost', $port = 8125, $timeout = 3, $mtu = 1500) {
        $this->host = (string)$host;
        $this->port = (int)$port;
        $this->mtu = (int)$mtu;

        $this->timeout = ($timeout === null) ? null : (int)$timeout;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @return int
     */
    public function getTimeout() {
        return $this->timeout;
    }

    /**
     * sends a message to the UDP socket
     *
     * @param string $message
     *
     * @codeCoverageIgnore
     * this is ignored because it writes to an actual socket and is not testable
     */
    public function send($message) {
        // prevent from sending empty or non-sense metrics
        if ($message === '' || !is_string($message)) {
            return;
        }

        if (!$this->isConnected()) {
            $this->connect($this->host, $this->port, $this->timeout);
        }

        $this->writeToSocket($message);
    }

    /**
     * sends multiple messages to statsd
     *
     * @param array $messages
     */
    public function sendMessages(array $messages) {
        $message = implode("\n", $messages);

        if (strlen($message) > $this->mtu) {
            $messageBatches = $this->cutIntoMtuSizedMessages($messages);

            foreach ($messageBatches as $messageBatch) {
                $this->send(implode("\n", $messageBatch));
            }
        } else {
            $this->send($message);
        }
    }

    /**
     * connect to the socket
     *
     * @param string $host
     * @param int $port
     * @param int|null $timeout
     */
    abstract protected function connect($host, $port, $timeout);

    /**
     * checks whether the socket connection is alive
     *
     * @return bool
     */
    abstract protected function isConnected();

    /**
     * writes a message to the socket
     *
     * @param string $message
     */
    abstract protected function writeToSocket($message);

    /**
     * @param array $messages
     *
     * @return array
     */
    private function cutIntoMtuSizedMessages(array $messages) {
        $index = 0;
        $sizedMessages = [];
        $packageLength = 0;

        foreach ($messages as $message) {
            $messageLength = strlen($message);

            if ($messageLength + $packageLength > $this->mtu) {
                ++$index;
                $packageLength = 0;
            }

            $sizedMessages[$index][] = $message;
            $packageLength += $messageLength;
        }

        return $sizedMessages;
    }
}
