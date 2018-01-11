<?php

namespace Resque\StatsD\Connection;


/**
 * encapsulates the connection to the statsd service in UDP mode (standard)
 *
 * @codeCoverageIgnore
 */
class UdpSocket extends Socket {
    /**
     * the used UDP socket resource
     *
     * @var resource|null|false
     */
    private $socket;

    /** @var bool */
    private $isConnected;

    /**
     * sends a message to the socket
     *
     * @param string $message
     *
     * @codeCoverageIgnore
     * this is ignored because it writes to an actual socket and is not testable
     */
    public function send($message) {
        try {
            parent::send($message);
        } catch (\Exception $e) {
            // ignore it: stats logging failure shouldn't stop the whole app
        }
    }

    /**
     * @param string $host
     * @param int $port
     * @param int|null $timeout
     */
    protected function connect($host, $port, $timeout) {
        $errorNumber = null;
        $errorMessage = null;

        $url = 'udp://' . $host;

        $this->socket = fsockopen($url, $port, $errorNumber, $errorMessage, $timeout);

        $this->isConnected = (bool)$this->socket;
    }

    /**
     * checks whether the socket connection is alive
     * only tries to connect once
     * ever after isConnected will return true,
     * because $this->socket is then false
     *
     * @return bool
     */
    protected function isConnected() {
        return $this->isConnected;
    }

    /**
     * @param string $message
     */
    protected function writeToSocket($message) {
        fwrite($this->socket, $message);
    }
}
