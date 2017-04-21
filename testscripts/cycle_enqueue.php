<?php
require_once '/home/dmolnar/work/qu/php-resqueue-serial/vendor/autoload.php';

require_once 'shared.php';

Resque::setBackend("localhost:6499");

Resque_Redis::prefix(ResqueSerial::VERSION);

unlink('/tmp/serialjob.txt');

$i = 8;
while (true) {
    Resque::enqueue('example_queue', __TestJob::class, ['arg' => $i]);
    //$i = ($i + 1) % 6 + 6;
    sleep(4);
}