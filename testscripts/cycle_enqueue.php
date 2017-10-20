<?php

require_once '../vendor/autoload.php';

require_once 'shared.php';

Resque::setBackend("localhost:6379");

unlink('/tmp/serialjob.txt');

//$i = 0;
//while ($i < 50) {
//    $i++;
//    Resque::enqueue('example_queue', new __SerialJob());
//    usleep(100000);
//}


//Resque::enqueue('example_queue', new Descriptor(__FailJob::class, [
//        'arg' => 'test',
//        'uniqueId' => 'testId'
//]));


var_dump(\Resque\UniqueList::add('plain'));
var_dump(\Resque\UniqueList::add('new'));
var_dump(\Resque\UniqueList::edit('new', "EDITED"));
var_dump(\Resque\UniqueList::edit('fake', 'TEST'));
//var_dump(\Resque\UniqueList::remove('new'));
//var_dump(\Resque\UniqueList::remove('fake'));

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