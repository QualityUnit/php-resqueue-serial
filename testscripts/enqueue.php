<?php

require_once '../vendor/autoload.php';

use Resque\Config\GlobalConfig;
use Resque\Init\InitProcess;

ini_set('display_errors', true);
error_reporting(E_ALL);

unlink('/tmp/serialjob.txt');

GlobalConfig::initialize('./../resources/config.yml');

$proc = new InitProcess();

$proc->start();
$proc->maintain();