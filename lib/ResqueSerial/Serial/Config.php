<?php


namespace ResqueSerial\Serial;


class Config {

    /**
     * @var mixed[]
     */
    private $data;

    /**
     * Config constructor.
     *
     * @param mixed[] $data
     */
    public function __construct(array $data = []) {
        $this->data = $data;
    }

    /**
     * @return mixed[]
     */
    public function getData() {
        return $this->data;
    }

    /**
     * @param $queueId
     * @return string
     */
    public function getQueue($queueId) {
        return $this->data['queues'][$queueId];
    }

    /**
     * @return int
     */
    public function getQueueCount() {
        return $this->data['queueCount'];
    }
}