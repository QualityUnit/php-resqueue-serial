#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;
use Resque\Redis;

if($argc !== 2 || !file_exists($argv[1])) {
    throw new Exception('Expected config file as command line parameter');
}

$config = GlobalConfig::initialize($argv[1]);

Redis::prefix(Resque::VERSION_PREFIX);
Resque::setBackend($config->getBackend());

$process = new InitProcess();
$process->start();
$process->maintain();