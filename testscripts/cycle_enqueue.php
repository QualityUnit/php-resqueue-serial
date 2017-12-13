<?php

require_once '../vendor/autoload.php';

require_once 'shared.php';

Resque::setBackend("localhost:6379");

unlink('/tmp/serialjob.txt');

//$i = 0;
//while ($i < 50) {
//    $i++;
    Resque::enqueue('example_queue', new UidDescriptor(__RescheduleJob::class, []));
//}


//Resque::enqueue('example_queue', new Descriptor(__FailJob::class, [
//        'arg' => 'test',
//        'uniqueId' => 'testId'
//]));


//Resque::enqueue('example_queue', RunApplicationTask::class, [
//        'include_path' => 'something.php',
//        'job_args' => [],
//        'job_class' => '__ApplicationTask',
//]);

//$start = new DateTime('2017-9-25T14:40:0');
//echo $start->format(DateTime::ATOM) . PHP_EOL;
//
//$interval = new DateInterval('PT1M');
//
//echo Resque::planCreate($start, $interval, 'example_queue', new Descriptor(__TestJob::class, []));