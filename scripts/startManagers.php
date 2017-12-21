#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;

if($argc !== 2 || !file_exists($argv[1])) {
    throw new Exception('Expected config file as command line parameter');
}

$config = GlobalConfig::initialize($argv[1]);

Resque::setBackend($config->getBackend());

$process = new InitProcess();
$process->start();
try {
    $process->maintain();
} catch (Throwable $t) {
    \Resque\Log::critical("Maintain process failed with: {$t->getMessage()}", ['exception' => $t]);
    throw $t;
}