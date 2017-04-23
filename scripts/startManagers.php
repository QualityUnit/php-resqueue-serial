#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

Resque_Redis::prefix(ResqueSerial::VERSION);

$proc = new \ResqueSerial\Init\Process();

$proc->start();
$proc->maintain();