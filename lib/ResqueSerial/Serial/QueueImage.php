<?php


namespace ResqueSerial\Serial;


use ResqueSerial\Key;
use ResqueSerial\Lock;
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
    public function __construct($serialQueue) {
        $this->serialQueue = $serialQueue;
        $this->parts = explode('~', $serialQueue);
        if (count($this->parts) != 2) {
            Log::main()->warning("Queue passed to QueueImage constructor ($serialQueue)."
                    . " This is probably a bug.");
        }
        $this->configManager = new ConfigManager($serialQueue);
    }

    public static function create($queue, $serialId) {
        return new self($queue . '~' . $serialId);
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
        return new Lock(Key::queueLock($this->serialQueue));
    }
}