<?php


namespace ResqueSerial;


class Lock {

    private $lockKey;
    private $lockValue;
    private $time;

    /**
     * Lock constructor.
     *
     * @param $key
     * @param int $time
     */
    public function __construct($key, $time = 5000) {
        $this->time = $time;
        $this->lockKey = $key;
        $this->lockValue = md5(microtime());
    }


    public function acquire() {
        $response = \Resque::redis()->set($this->lockKey, $this->lockValue, [
                0 => 'nx',
                'px' => $this->time
        ]);
        var_dump($response);
        if (!$response) {
            return false;
        }

        return true;
    }

    public function release() {
        $script = "if redis.call('get',KEYS[1]) == ARGV[1] then return redis.call('del',KEYS[1]) else return 0 end";
        $response = \Resque::redis()->eval($script,
                [\Resque_Redis::getPrefix() . $this->lockKey],
                [$this->lockValue]);
        var_dump($response);
        if (!$response) {
            return false;
        }

        return true;
    }
}