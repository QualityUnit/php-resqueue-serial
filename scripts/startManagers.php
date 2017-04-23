#!/usr/bin/env php
<?php

use ResqueSerial\Redis;

require_once 'bootstrap.php';

Redis::prefix(ResqueSerial::VERSION);

$proc = new \ResqueSerial\Init\Process();

$proc->start();
$proc->maintain();