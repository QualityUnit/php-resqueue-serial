<?php
use ResqueSerial\Redis;
use ResqueSerial\Task\RunApplicationTask;

require_once '/home/dmolnar/work/qu/php-resqueue-serial/vendor/autoload.php';

require_once 'shared.php';

Resque::setBackend("localhost:6379");

Redis::prefix(ResqueSerial::VERSION);

unlink('/tmp/serialjob.txt');

$i = 0;
while ($i < 1000) {
    $i++;
    Resque::enqueue('example_queue', __TestJob::class, ['arg' => $i]);
    //$i = ($i + 1) % 6 + 6;
    //sleep(4);
//    usleep(5);
}


//Resque::enqueue('example_queue', __FailJob::class, ['arg' => 'test']);


//Resque::enqueue('example_queue', RunApplicationTask::class, [
//        'include_path' => 'something.php',
//        'job_args' => [],
//        'job_class' => '__ApplicationTask',
//]);