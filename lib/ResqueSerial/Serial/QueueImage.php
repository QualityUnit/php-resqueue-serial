<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;
use ResqueSerial\QueueLock;
use ResqueSerial\Log;

class QueueImage {

    /** @var string */
    private $serialQueue;
    /** @var string[] */
    private $parts;
    /** @var ConfigManager */
    private $configManager;

    /**
     * Serial QueueImage constructor.
     *
     * @param string $serialQueue
     */
    private function __construct($serialQueue) {
        $this->serialQueue = $serialQueue;
        $this->parts = explode('~', $serialQueue);
        if (count($this->parts) != 2) {
            Log::main()->warning("Queue passed to QueueImage constructor ($serialQueue)."
                    . " This is probably a bug.");
        }
        $this->configManager = new ConfigManager($serialQueue);
    }

    /**
     * @return \Iterator
     */
    public static function all() {
        $keys = \Resque::redis()->keys(Key::serialQueue('*'));
        return new __QueueIterator($keys, Key::serialQueue(''));
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
     *
     * @param $secondarySerialId
     *
     * @return string subqueue name if queue count is greater than 1, otherwise main queue name
     */
    public function generateSubqueueName($secondarySerialId) {
        $config = $this->configManager->getLatest();

        if($config->getQueueCount() < 2) {
            return $this->serialQueue;
        }

        $hashNum = hexdec(substr(md5($secondarySerialId), -4));
        $subQueue = $this->serialQueue . $config->getQueuePostfix($hashNum % $config->getQueueCount());

        return $subQueue;
    }

    public function getParentQueue() {
        return @$this->parts[0];
    }

    public function getQueue() {
        return $this->serialQueue;
    }

    public function getSerialId() {
        return @$this->parts[1];
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

    public function next() {
        $this->iterator->next();
    }

    public function key() {
        return $this->iterator->key();
    }

    public function valid() {
        return $this->iterator->valid();
    }

    public function rewind() {
        $this->iterator->rewind();
    }
}