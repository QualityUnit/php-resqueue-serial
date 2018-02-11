<?php

namespace Resque\StatsD;

abstract class AbstractClient {

    /** @var string */
    protected $namespace;

    /**
     * @param string $namespace global key namespace
     */
    public function __construct($namespace = '') {
        $this->namespace = (string)$namespace;
    }

    /**
     * sends a count to statsd
     *
     * @param string $key
     * @param int $value
     * @param int $sampleRate (optional) the default is 1
     * @param array $tags
     */
    public function count($key, $value, $sampleRate = 1, array $tags = []) {
        $this->send($key, (int)$value, 'c', $sampleRate, $tags);
    }

    /**
     * decrements the key by 1
     *
     * @param string $key
     * @param int $sampleRate
     * @param array $tags
     */
    public function decrement($key, $sampleRate = 1, array $tags = []) {
        $this->count($key, -1, $sampleRate, $tags);
    }

    /**
     * sends a gauge, an arbitrary value to StatsD
     *
     * @param string $key
     * @param string|int $value
     * @param array $tags
     */
    public function gauge($key, $value, array $tags = []) {
        $this->send($key, $value, 'g', 1, $tags);
    }

    /**
     * increments the key by 1
     *
     * @param string $key
     * @param int $sampleRate
     * @param array $tags
     */
    public function increment($key, $sampleRate = 1, array $tags = []) {
        $this->count($key, 1, $sampleRate, $tags);
    }

    /**
     * sends a set member
     *
     * @param string $key
     * @param int $value
     * @param array $tags
     */
    public function set($key, $value, array $tags = []) {
        $this->send($key, $value, 's', 1, $tags);
    }

    /**
     * sends a timing to statsd (in ms)
     *
     * @param string $key
     * @param int $value the timing in ms
     * @param int $sampleRate the sample rate, if < 1, statsd will send an average timing
     * @param array $tags
     */
    public function timing($key, $value, $sampleRate = 1, array $tags = []) {
        $this->send($key, $value, 'ms', $sampleRate, $tags);
    }

    /**
     * @param string $dataToSend
     */
    abstract protected function sendRawData($dataToSend);

    /**
     * actually sends a message to to the daemon and returns the sent message
     *
     * @param string $key
     * @param int $value
     * @param string $type
     * @param float $sampleRate
     * @param array $tags
     */
    private function send($key, $value, $type, $sampleRate, array $tags = []) {
        if (mt_rand() / mt_getrandmax() > $sampleRate) {
            return;
        }

        if ('' !== $this->namespace) {
            $key = $this->namespace . '.' . $key;
        }

        $message = $key . ':' . $value . '|' . $type;

        if ($sampleRate < 1) {
            $sampledData = $message . '|@' . $sampleRate;
        } else {
            $sampledData = $message;
        }

        if (!empty($tags)) {
            $sampledData .= '|#';
            $tagArray = [];
            foreach ($tags as $tagKey => $tagValue) {
                $tagArray[] = ($tagKey . ':' . $tagValue);
            }
            $sampledData .= implode(',', $tagArray);
        }

        $this->sendRawData($sampledData);
    }
}