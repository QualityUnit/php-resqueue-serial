<?php
use Resque\Redis;

require_once '../vendor/autoload.php';

require_once 'shared.php';

Resque::setBackend("localhost:6379");

Redis::prefix(Resque::VERSION);

unlink('/tmp/serialjob.txt');

$i = 0;
while ($i < 50) {
    $i++;
    Resque::enqueue('example_queue', new __SerialJob());
    usleep(100000);
}


//Resque::enqueue('example_queue', __FailJob::class, ['arg' => 'test']);


//Resque::enqueue('example_queue', RunApplicationTask::class, [
//        'include_path' => 'something.php',
//        'job_args' => [],
//        'job_class' => '__ApplicationTask',
//]);