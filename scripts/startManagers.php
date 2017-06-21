#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;
use Resque\Redis;

$config = GlobalConfig::initialize('/etc/resque-serial/config.yml');

Redis::prefix(Resque::VERSION);
Resque::setBackend($config->getBackend());

$process = new InitProcess();
$process->start();
$process->maintain();