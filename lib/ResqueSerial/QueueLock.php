<?php


namespace ResqueSerial;


class QueueLock {

    const RELEASE_SCRIPT = <<<LUA
if redis.call('get',KEYS[1]) == ARGV[1] then return redis.call('del',KEYS[1]) else return 0 end
LUA;

    private $lockKey;
    private $lockValue;
    private $time;

    /**
     * Lock constructor.
     *
     * @param $queue
     * @param int $time
     */
    public function __construct($queue, $time = 5000) {
        $this->time = $time;
        $this->lockKey = Key::queueLock($queue);
        $this->lockValue = md5(microtime());
    }

    public static function exists($queue) {
        return \Resque::redis()->get(Key::queueLock($queue)) !== false;
    }

    public function acquire() {
        $response = \Resque::redis()->set($this->lockKey, $this->lockValue, [
                0 => 'nx', // only if it does not exist yet
                'px' => $this->time // rexpire time, in millis
        ]);
        if (!$response) {
            return false;
        }

        return true;
    }

    public function release() {
        $response = \Resque::redis()->eval(
                self::RELEASE_SCRIPT,
                [\Resque_Redis::getPrefix() . $this->lockKey],
                [$this->lockValue]
        );
        if (!$response) {
            return false;
        }

        return true;
    }
}