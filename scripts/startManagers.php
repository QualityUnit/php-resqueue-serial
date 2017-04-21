#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

Resque_Redis::prefix(ResqueSerial::VERSION);

Resque_Failure::setBackend(ResqueSerial\Failure\RedisRetry::class);

$proc = new \ResqueSerial\Init\Process();

$proc->start();
$proc->maintain();