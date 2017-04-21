<?php

require_once '/home/dmolnar/work/qu/php-resqueue-serial/vendor/autoload.php';

require_once 'shared.php';

Resque_Redis::prefix(ResqueSerial::VERSION);

unlink('/tmp/serialjob.txt');

$PATH = __DIR__ . '/../resources/config.yml';

if($PATH) {
    \ResqueSerial\Init\GlobalConfig::$PATH = $PATH;
}

$proc = new \ResqueSerial\Init\Process();

$proc->start();
$proc->maintain();