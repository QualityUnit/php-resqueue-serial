<?php


namespace ResqueSerial\Serial;


class ConfigManager {
    private $serialQueue;


    /**
     * Config constructor.
     *
     * @param $serialQueue
     */
    public function __construct($serialQueue) {
        $this->serialQueue = $serialQueue;
    }

    private function load() {
        //todo
    }
}