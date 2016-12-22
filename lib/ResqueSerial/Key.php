<?php


namespace ResqueSerial;


class Key {

    /**
     * @var string
     */
    private $key;

    /**
     * _KBuild constructor.
     *
     * @param string $key
     */
    private function __construct($key) {
        $this->key = $key;
    }

    public static function serialCheckpoints($queue) {
        return Key::serial()->add($queue)->add('checkpoints')->get();
    }

    /**
     * @param $queue
     * @return string
     */
    public static function serialCompletedCount($queue) {
        return Key::serial()->add($queue)->add('completed_count')->get();
    }

    /**
     * @param $queue
     * @return string
     */
    public static function serialConfig($queue) {
        return Key::serial()->add($queue)->add('config')->get();
    }

    /**
     * @return Key
     */
    private static function serial() {
        return new self('serial');
    }

    /**
     * @param $string
     * @return $this
     */
    private function add($string) {
        $this->key .= ':' . $string;
        return $this;
    }

    /**
     * @return string
     */
    private function get() {
        return $this->key;
    }
}