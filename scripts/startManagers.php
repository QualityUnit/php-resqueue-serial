#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

use ResqueSerial\Init\GlobalConfig;
use ResqueSerial\Redis;

if($argc !== 2 || !file_exists($argv[1])) {
    throw new Exception('Expected config file as command line parameter');
}

GlobalConfig::$PATH = $argv[1];

Redis::prefix(ResqueSerial::VERSION);

$proc = new \ResqueSerial\Init\Process();

$proc->start();
$proc->maintain();