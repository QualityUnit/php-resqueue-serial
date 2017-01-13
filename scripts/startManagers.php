#!/usr/bin/env php
<?php

require_once 'bootstrap.php';

$QUEUE = getenv('QUEUE');
if (empty($QUEUE)) {
    die("Set QUEUE env var containing the list of queues to work.\n");
}

/**
 * REDIS_BACKEND can have simple 'host:port' format or use a DSN-style format like this:
 * - redis://user:pass@host:port
 * Note: the 'user' part of the DSN URI is required but is not used.
 */
$REDIS_BACKEND = getenv('REDIS_BACKEND');

// A redis database number
$REDIS_BACKEND_DB = getenv('REDIS_BACKEND_DB');
if (!empty($REDIS_BACKEND)) {
    if (empty($REDIS_BACKEND_DB)) {
        Resque::setBackend($REDIS_BACKEND);
    } else {
        Resque::setBackend($REDIS_BACKEND, $REDIS_BACKEND_DB);
    }
}

$logLevel = false;
$LOGGING = getenv('LOGGING');
$VERBOSE = getenv('VERBOSE');
$VVERBOSE = getenv('VVERBOSE');
if (!empty($LOGGING) || !empty($VERBOSE)) {
    $logLevel = true;
} else if (!empty($VVERBOSE)) {
    $logLevel = true;
}

$logger = new Resque_Log($logLevel);

$BLOCKING = getenv('BLOCKING') !== false;

$interval = 5;
$INTERVAL = getenv('INTERVAL');
if (!empty($INTERVAL)) {
    $interval = $INTERVAL;
}

$count = 1;
$COUNT = getenv('COUNT');
if (!empty($COUNT) && $COUNT > 1) {
    $count = $COUNT;
}

$PREFIX = getenv('PREFIX');
if (!empty($PREFIX)) {
    $logger->log(Psr\Log\LogLevel::INFO, 'Prefix set to {prefix}', array('prefix' => $PREFIX));
    Resque_Redis::prefix($PREFIX);
}

for ($i = 0; $i < $count; ++$i) {
	try {
        $pid = Resque::fork();
        if($pid === false) {
        	throw new Exception();
		}

        if (!$pid) {
            $queues = explode(',', $QUEUE);
            $worker = new \ResqueSerial\Worker($queues);
            $worker->setLogger($logger);
            $logger->log(Psr\Log\LogLevel::NOTICE, 'Starting worker {worker}', array('worker' => $worker));
            $worker->work($interval, $BLOCKING);
            break;
        }
    } catch (Exception $e) {
        $logger->log(Psr\Log\LogLevel::EMERGENCY, 'Could not fork worker {count}', array('count' => $i));
        die();
    }
}