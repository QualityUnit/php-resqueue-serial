<?php


namespace Test;

use PHPUnit\Framework\TestCase;
use Resque\Resque;

class RedisTestCase extends TestCase {

    protected function setUp() {
        parent::setUp();
        Resque::setBackend('localhost:6379');
        Resque::redis()->flushDb();
        \Resque\Redis::prefix('resqu-test');
    }

    protected function addKeys(array $keys) {
        $redis = Resque::redis();
        foreach ($keys as $key => $value) {
            $redis->set($key, $value);
        }
    }

    protected function assertKeyValue($key, string $value) {
        $actual = Resque::redis()->get($key);
        self::assertEquals($value, $actual, "Key $key value does not match. Expected: $value, Actual: $actual");
    }

    protected function assertFirstListValue($listKey, $value) {
        $actual = Resque::redis()->lRange($listKey, -1, -1)[0] ?? false;
        self::assertEquals($value, $actual, "Key $listKey value does not match. Expected: $value, Actual: $actual");
    }

    protected function assertKeyExists($key, $exists = true) {
        self::assertEquals($exists, Resque::redis()->exists($key));
    }
}