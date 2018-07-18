<?php

namespace Resque;

trait SingletonTrait {

    private static $instance;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = self::createInstance();
        }

        return self::$instance;
    }

    protected static function createInstance() {
        return new static();
    }
}