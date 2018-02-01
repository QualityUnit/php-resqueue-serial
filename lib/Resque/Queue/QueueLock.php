<?php


namespace Resque\Queue;


use Resque;
use Resque\Key;
use Resque\Redis;

class QueueLock {

    const DEFAULT_TIME = 30000;

    const RELEASE_SCRIPT = <<<LUA
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;
    const ACQUIRE_SCRIPT = <<<LUA
local val = redis.call('get', KEYS[1])
if not val or val == ARGV[1] then
    return redis.call('set', KEYS[1], ARGV[1], 'PX', ARGV[2])
else
    return 0
end
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
    public function __construct($queue, $time = self::DEFAULT_TIME) {
        $this->time = $time;
        $this->lockKey = Key::queueLock($queue);
        $this->lockValue = md5(microtime());
    }

    public static function exists($queue) {
        return Resque::redis()->get(Key::queueLock($queue)) !== false;
    }

    public function acquire($time = null) {
        if ($time == null) {
            $time = $this->time;
        }
        $response = Resque::redis()->eval(
                self::ACQUIRE_SCRIPT,
                [$this->lockKey],
                [$this->lockValue, $time]
        );
        if (!$response) {
            return false;
        }

        return true;
    }

    public function release() {
        $response = Resque::redis()->eval(
                self::RELEASE_SCRIPT,
                [$this->lockKey],
                [$this->lockValue]
        );
        if (!$response) {
            return false;
        }

        return true;
    }
}