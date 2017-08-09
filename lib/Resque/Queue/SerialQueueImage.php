<?php

namespace Resque\Queue;

use Resque;
use Resque\Key;
use Resque\Log;
use Resque\Redis;
use Resque\Stats;

class SerialQueueImage {

    /** @var string */
    private $serialQueue;
    /** @var string[] */
    private $parts;
    /** @var ConfigManager */
    private $configManager;

    /**
     * @param string $serialQueue
     */
    private function __construct($serialQueue) {
        $this->serialQueue = $serialQueue;
        $this->parts = explode('~', $serialQueue);
        if (count($this->parts) != 2) {
            $e = new \Exception();
            Log::warning("Queue passed to SerialQueueImage constructor ($serialQueue)."
                . ' This is probably a bug.', ['exception' => $e]);
        }
        $this->configManager = new ConfigManager($serialQueue);
    }

    /**
     * @return \Iterator
     */
    public static function all() {
        $keys = Resque::redis()->keys(Key::serialQueue('*'));
        $prefix = Redis::getPrefix() . Key::serialQueue('');
        return new __QueueIterator($keys, strlen($prefix));
    }

    public static function create($queue, $serialId) {
        return new self($queue . '~' . $serialId);
    }

    public static function fromName($serialQueue) {
        return new self($serialQueue);
    }

    public function config() {
        return $this->configManager;
    }

    /**
     * Generates and returns subqueue name for specified secondary serial ID.
     * @param $secondarySerialId
     * @return string subqueue name if queue count is greater than 1, otherwise main queue name
     */
    public function generateSubqueueName($secondarySerialId) {
        $config = $this->configManager->getLatest();

        if ($config->getQueueCount() < 2) {
            return $this->serialQueue;
        }

        $hashNum = hexdec(substr(md5($secondarySerialId), -4));
        $subQueue = $this->serialQueue . $config->getQueuePostfix($hashNum % $config->getQueueCount());

        return $subQueue;
    }

    public function getParentQueue() {
        return @$this->parts[0];
    }

    /**
     * @return SerialQueue
     */
    public function getQueue() {
        $stats = new Resque\Stats\QueueStats($this->getParentQueue());
        return new SerialQueue($this->serialQueue, $stats);
    }

    public function getQueueName() {
        return $this->serialQueue;
    }

    public function getSerialId() {
        return @$this->parts[1];
    }

    /**
     * @param String $postfix
     *
     * @return SerialQueue
     */
    public function getSubQueue($postfix) {
        $stats = new Resque\Stats\QueueStats($this->getParentQueue());
        return new SerialQueue($this->serialQueue . $postfix, $stats);
    }

    public function lockExists() {
        return QueueLock::exists($this->serialQueue);
    }

    public function newLock() {
        return new QueueLock($this->serialQueue);
    }
}

class __QueueIterator implements \Iterator {

    /** @var \ArrayIterator */
    private $iterator;
    /** @var int */
    private $prefixLength;

    public function __construct(array $array, $prefixLength) {
        $this->iterator = new \ArrayIterator($array);
        $this->prefixLength = $prefixLength;
    }

    public function current() {
        return substr($this->iterator->current(), $this->prefixLength);
    }

    public function key() {
        return $this->iterator->key();
    }

    public function next() {
        $this->iterator->next();
    }

    public function rewind() {
        $this->iterator->rewind();
    }

    public function valid() {
        return $this->iterator->valid();
    }
}