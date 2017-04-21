<?php


namespace ResqueSerial\Failure;


use ResqueSerial\Init\GlobalConfig;
use ResqueSerial\Log;

class RedisRetry implements \Resque_Failure_Interface {

    public function __construct($payload, $exception, $worker, $queue) {
        $job = new \Resque_Job($queue, $payload);

        $retried_by = null;
        $retry_text = null;
        if ($job->getFails() < GlobalConfig::instance()->getMaxTaskFails()) {
            try {
                $retried_by = $job->recreate();
                $retry_text = $retried_by;
            } catch (\Exception $e) {
                Log::local()->critical("Failed to recreate job $job");
                $retry_text = "Error: " . $e->getMessage();
            }
        }

        $data = new \stdClass;
        $data->failed_at = strftime('%a %b %d %H:%M:%S %Z %Y');
        $data->payload = $payload;
        $data->exception = get_class($exception);
        $data->error = $exception->getMessage();
        $data->backtrace = explode("\n", $exception->getTraceAsString());
        $data->worker = (string)$worker;
        $data->queue = $queue;
        $data->retried_by = $retry_text;
        $data = json_encode($data);

        if ($retried_by !== null) {
            \Resque_Stat::incr('retries');
            \Resque_Stat::incr('retries:' . (string)$worker);
            \Resque::redis()->rpush('retries', $data);
        } else {
            \Resque_Stat::incr('failed');
            \Resque_Stat::incr('failed:' . (string)$worker);
            \Resque::redis()->rpush('failed', $data);
        }
    }
}