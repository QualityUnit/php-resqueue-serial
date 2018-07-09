<?php

ini_set('display_errors', true);
error_reporting(E_ALL);

require_once '../vendor/autoload.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;

require_once 'testjobs.php';

GlobalConfig::initialize('./../resources/config.yml');

$proc = new InitProcess();

$proc->start();
$proc->maintain();