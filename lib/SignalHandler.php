<?php


namespace Resque;


class SignalHandler {

    /** @var callable[] */
    private $handlers = [];
    /** @var self */
    private static $instance;

    private function __construct() {
    }

    public static function dispatch() {
        pcntl_signal_dispatch();
    }

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getHandler($signal) {
        return $this->handlers[$signal] ?? SIG_DFL;
    }

    public function register($signal, $handler) {
        if (function_exists('pcntl_signal')) {
            pcntl_signal($signal, $handler);
            $this->handlers[$signal] = $handler;
        }

        return $this;
    }

    public function unregister($signal) {
        unset($this->handlers[$signal]);
        if (function_exists('pcntl_signal')) {
            pcntl_signal($signal, SIG_DFL);
        }

        return $this;
    }

    public function unregisterAll() {
        foreach ($this->handlers as $signal => $handler) {
            $this->unregister($signal);
        }

        return $this;
    }
}