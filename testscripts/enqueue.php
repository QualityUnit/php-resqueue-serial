<?php

require_once '../vendor/autoload.php';

require_once 'shared.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;

unlink('/tmp/serialjob.txt');

GlobalConfig::initialize('./../resources/config.yml');

$proc = new InitProcess();

$proc->start();
$proc->maintain();