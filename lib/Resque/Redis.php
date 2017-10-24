<?php

namespace Resque;

use Credis_Client;
use CredisException;
use Resque\Api\RedisException;

/**
 * Wrap Credis to add namespace support and various helper methods.
 *
 * @package        Resque/Redis
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 * COPIED FROM CREDIS CLIENT PHPDOC
 * Server/Connection:
 * @method Credis_Client               pipeline()
 * @method Credis_Client               multi()
 * @method Credis_Client               watch(string ...$keys)
 * @method Credis_Client               unwatch()
 * @method array                       exec()
 * @method string|Credis_Client        flushAll()
 * @method string|Credis_Client        flushDb()
 * @method array|Credis_Client         info(string $section = null)
 * @method bool|array|Credis_Client    config(string $setGet, string $key, string $value = null)
 * @method array|Credis_Client         role()
 * @method array|Credis_Client         time()
 *
 * Keys:
 * @method int|Credis_Client           del(string|string[] $key)
 * @method int|Credis_Client           exists(string $key)
 * @method int|Credis_Client           expire(string $key, int $seconds)
 * @method int|Credis_Client           expireAt(string $key, int $timestamp)
 * @method array|Credis_Client         keys(string $key)
 * @method int|Credis_Client           persist(string $key)
 * @method bool|Credis_Client          rename(string $key, string $newKey)
 * @method bool|Credis_Client          renameNx(string $key, string $newKey)
 * @method array|Credis_Client         sort(string $key, string $arg1, string $valueN = null)
 * @method int|Credis_Client           ttl(string $key)
 * @method string|Credis_Client        type(string $key)
 *
 * Scalars:
 * @method int|Credis_Client           append(string $key, string $value)
 * @method int|Credis_Client           decr(string $key)
 * @method int|Credis_Client           decrBy(string $key, int $decrement)
 * @method bool|string|Credis_Client   get(string $key)
 * @method int|Credis_Client           getBit(string $key, int $offset)
 * @method string|Credis_Client        getRange(string $key, int $start, int $end)
 * @method string|Credis_Client        getSet(string $key, string $value)
 * @method int|Credis_Client           incr(string $key)
 * @method int|Credis_Client           incrBy(string $key, int $decrement)
 * @method array|Credis_Client         mGet(array $keys)
 * @method bool|Credis_Client          mSet(array $keysValues)
 * @method int|Credis_Client           mSetNx(array $keysValues)
 * @method bool|Credis_Client          set(string $key, string $value, int | array $options = null)
 * @method int|Credis_Client           setBit(string $key, int $offset, int $value)
 * @method bool|Credis_Client          setEx(string $key, int $seconds, string $value)
 * @method int|Credis_Client           setNx(string $key, string $value)
 * @method int |Credis_Client          setRange(string $key, int $offset, int $value)
 * @method int|Credis_Client           strLen(string $key)
 *
 * Sets:
 * @method int|Credis_Client           sAdd(string $key, mixed $value, string $valueN = null)
 * @method int|Credis_Client           sRem(string $key, mixed $value, string $valueN = null)
 * @method array|Credis_Client         sMembers(string $key)
 * @method array|Credis_Client         sUnion(mixed $keyOrArray, string $valueN = null)
 * @method array|Credis_Client         sInter(mixed $keyOrArray, string $valueN = null)
 * @method array |Credis_Client        sDiff(mixed $keyOrArray, string $valueN = null)
 * @method string|Credis_Client        sPop(string $key)
 * @method int|Credis_Client           sCard(string $key)
 * @method int|Credis_Client           sIsMember(string $key, string $member)
 * @method int|Credis_Client           sMove(string $source, string $dest, string $member)
 * @method string|array|Credis_Client  sRandMember(string $key, int $count = null)
 * @method int|Credis_Client           sUnionStore(string $dest, string $key1, string $key2 = null)
 * @method int|Credis_Client           sInterStore(string $dest, string $key1, string $key2 = null)
 * @method int|Credis_Client           sDiffStore(string $dest, string $key1, string $key2 = null)
 *
 * Hashes:
 * @method bool|int|Credis_Client      hSet(string $key, string $field, string $value)
 * @method bool|Credis_Client          hSetNx(string $key, string $field, string $value)
 * @method bool|string|Credis_Client   hGet(string $key, string $field)
 * @method bool|int|Credis_Client      hLen(string $key)
 * @method bool|Credis_Client          hDel(string $key, string $field)
 * @method array|Credis_Client         hKeys(string $key, string $field)
 * @method array|Credis_Client         hVals(string $key)
 * @method array|Credis_Client         hGetAll(string $key)
 * @method bool|Credis_Client          hExists(string $key, string $field)
 * @method int|Credis_Client           hIncrBy(string $key, string $field, int $value)
 * @method bool|Credis_Client          hMSet(string $key, array $keysValues)
 * @method array|Credis_Client         hMGet(string $key, array $fields)
 *
 * Lists:
 * @method array|null|Credis_Client    blPop(string $keyN, int $timeout)
 * @method array|null|Credis_Client    brPop(string $keyN, int $timeout)
 * @method array|null |Credis_Client   brPoplPush(string $source, string $destination, int $timeout)
 * @method string|null|Credis_Client   lIndex(string $key, int $index)
 * @method int|Credis_Client           lInsert(string $key, string $beforeAfter, string $pivot, string $value)
 * @method int|Credis_Client           lLen(string $key)
 * @method string|null|Credis_Client   lPop(string $key)
 * @method int|Credis_Client           lPush(string $key, mixed $value, mixed $valueN = null)
 * @method int|Credis_Client           lPushX(string $key, mixed $value)
 * @method array|Credis_Client         lRange(string $key, int $start, int $stop)
 * @method int|Credis_Client           lRem(string $key, int $count, mixed $value)
 * @method bool|Credis_Client          lSet(string $key, int $index, mixed $value)
 * @method bool|Credis_Client          lTrim(string $key, int $start, int $stop)
 * @method string|null|Credis_Client   rPop(string $key)
 * @method string|null|Credis_Client   rPoplPush(string $source, string $destination)
 * @method int|Credis_Client           rPush(string $key, mixed $value, mixed $valueN = null)
 * @method int |Credis_Client          rPushX(string $key, mixed $value)
 *
 * Sorted Sets:
 * @method int|Credis_Client           zAdd(string $key, double $score, string $value)
 * @method int|Credis_Client           zCard(string $key)
 * @method int|Credis_Client           zSize(string $key)
 * @method int|Credis_Client           zCount(string $key, mixed $start, mixed $stop)
 * @method int|Credis_Client           zIncrBy(string $key, double $value, string $member)
 * @method array|Credis_Client         zRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array|Credis_Client         zRevRangeByScore(string $key, mixed $start, mixed $stop, array $args = null)
 * @method int|Credis_Client           zRemRangeByScore(string $key, mixed $start, mixed $stop)
 * @method array|Credis_Client         zRange(string $key, mixed $start, mixed $stop, array $args = null)
 * @method array|Credis_Client         zRevRange(string $key, mixed $start, mixed $stop, array $args = null)
 * @method int|Credis_Client           zRank(string $key, string $member)
 * @method int|Credis_Client           zRevRank(string $key, string $member)
 * @method int|Credis_Client           zRem(string $key, string $member)
 * @method int|Credis_Client           zDelete(string $key, string $member)
 *
 * Pub/Sub
 * @method int |Credis_Client          publish(string $channel, string $message)
 * @method int|array|Credis_Client     pubsub(string $subCommand, $arg = null)
 *
 * Scripting:
 * @method string|int|Credis_Client    script(string $command, string $arg1 = null)
 * @method string|int|array|bool|Credis_Client eval(string $script, array $keys = null, array $args = null)
 * @method string|int|array|bool|Credis_Client evalSha(string $script, array $keys = null, array $args = null)
 * Special:
 * @method string        quit()
 */
class Redis {
    /**
     * Redis namespace
     * @var string
     */
    private static $defaultNamespace = 'resque:';

    /**
     * A default host to connect to
     */
    const DEFAULT_HOST = 'localhost';

    /**
     * The default Redis port
     */
    const DEFAULT_PORT = 6379;

    /**
     * The default Redis Database number
     */
    const DEFAULT_DATABASE = 0;

    /**
     * @var Credis_Client
     */
    private $driver = null;

    /**
     * @var array List of all commands in Redis that supply a key as their
     *    first argument. Used to prefix keys with the Resque namespace.
     */
    private $keyCommands = array(
            'exists',
            'del',
            'type',
            'keys',
            'expire',
            'ttl',
            'move',
            'set',
            'setex',
            'get',
            'getset',
            'hset',
            'hsetnx',
            'hget',
            'hlen',
            'hdel',
            'hkeys',
            'hvals',
            'hgetall',
            'hexists',
            'hincrby',
            'hmset',
            'hmget',
            'setnx',
            'incr',
            'incrby',
            'decr',
            'decrby',
            'rpush',
            'lpush',
            'llen',
            'lrange',
            'ltrim',
            'lindex',
            'lset',
            'lrem',
            'lpop',
            'blpop',
            'rpop',
            'sadd',
            'srem',
            'spop',
            'scard',
            'sismember',
            'smembers',
            'srandmember',
            'zadd',
            'zrem',
            'zrange',
            'zrevrange',
            'zrangebyscore',
            'zcard',
            'zscore',
            'zremrangebyscore',
            'sort',
            'rename',
            'rpoplpush'
    );

    /**
     * Set Redis namespace (prefix) default: resque
     * @param string $namespace
     */
    public static function prefix($namespace) {
        if (substr($namespace, -1) !== ':' && $namespace != '') {
            $namespace .= ':';
        }
        self::$defaultNamespace = $namespace;
    }

    /**
     * @param string|array $server A DSN or array
     * @param int $database A database number to select. However, if we find a valid database number
     * in the DSN the DSN-supplied value will be used instead and this parameter is ignored.
     * @throws RedisException
     */
    public function __construct($server, $database = null) {
        try {
            /** @noinspection PhpUnusedLocalVariableInspection */
            list($host, $port, $dsnDatabase, $user, $password, $options) = self::parseDsn($server);
            // $user is not used, only $password

            // Look for known Credis_Client options
            $timeout = isset($options['timeout']) ? intval($options['timeout']) : null;
            $persistent = isset($options['persistent']) ? $options['persistent'] : '';
            $maxRetries = isset($options['max_connect_retries'])
                    ? $options['max_connect_retries'] : 0;

            $this->driver = new Credis_Client($host, $port, $timeout, $persistent);
            $this->driver->setMaxConnectRetries($maxRetries);
            if ($password) {
                $this->driver->auth($password);
            }

            // If we have found a database in our DSN, use it instead of the `$database`
            // value passed into the constructor.
            if ($dsnDatabase !== false) {
                $database = $dsnDatabase;
            }

            if ($database !== null) {
                $this->driver->select($database);
            }
        } catch (CredisException $e) {
            throw new RedisException('Error communicating with Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse a DSN string, which can have one of the following formats:
     * - host:port
     * - redis://user:pass@host:port/db?option1=val1&option2=val2
     * - tcp://user:pass@host:port/db?option1=val1&option2=val2
     * - unix:///path/to/redis.sock
     * Note: the 'user' part of the DSN is not used.
     * @param string $dsn A DSN string
     * @return array An array of DSN compotnents, with 'false' values for any unknown components. e.g.
     *               [host, port, db, user, pass, options]
     */
    public static function parseDsn($dsn) {
        if ($dsn == '') {
            // Use a sensible default for an empty DNS string
            $dsn = 'redis://' . self::DEFAULT_HOST;
        }
        if (substr($dsn, 0, 7) === 'unix://') {
            return array(
                    $dsn,
                    null,
                    false,
                    null,
                    null,
                    null,
            );
        }
        $parts = parse_url($dsn);

        // Check the URI scheme
        $validSchemes = array('redis', 'tcp');
        if (isset($parts['scheme']) && !in_array($parts['scheme'], $validSchemes)) {
            throw new \InvalidArgumentException("Invalid DSN. Supported schemes are "
                    . implode(', ', $validSchemes));
        }

        // Allow simple 'hostname' format, which `parse_url` treats as a path, not host.
        if (!isset($parts['host']) && isset($parts['path'])) {
            $parts['host'] = $parts['path'];
            unset($parts['path']);
        }

        // Extract the port number as an integer
        $port = isset($parts['port']) ? intval($parts['port']) : self::DEFAULT_PORT;

        // Get the database from the 'path' part of the URI
        $database = false;
        if (isset($parts['path'])) {
            // Strip non-digit chars from path
            $database = intval(preg_replace('/[^0-9]/', '', $parts['path']));
        }

        // Extract any 'user' and 'pass' values
        $user = isset($parts['user']) ? $parts['user'] : false;
        $pass = isset($parts['pass']) ? $parts['pass'] : false;

        // Convert the query string into an associative array
        $options = array();
        if (isset($parts['query'])) {
            // Parse the query string into an array
            parse_str($parts['query'], $options);
        }

        return array(
                $parts['host'],
                $port,
                $database,
                $user,
                $pass,
                $options,
        );
    }

    /**
     * Magic method to handle all function requests and prefix key based
     * operations with the {self::$defaultNamespace} key prefix.
     * @param string $name The name of the method called.
     * @param array $args Array of supplied arguments to the method.
     * @return mixed Return value from Resident::call() based on the command.
     * @throws RedisException
     */
    public function __call($name, $args) {
        if (in_array(strtolower($name), $this->keyCommands)) {
            if (is_array($args[0])) {
                foreach ($args[0] AS $i => $v) {
                    $args[0][$i] = self::$defaultNamespace . $v;
                }
            } else {
                $args[0] = self::$defaultNamespace . $args[0];
            }
        }
        try {
            return $this->driver->__call($name, $args);
        } catch (CredisException $e) {
            throw new RedisException('Error communicating with Redis: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function getPrefix() {
        return self::$defaultNamespace;
    }

    public static function removePrefix($string) {
        $prefix = self::getPrefix();

        if (substr($string, 0, strlen($prefix)) == $prefix) {
            $string = substr($string, strlen($prefix), strlen($string));
        }

        return $string;
    }

    public function close() {
        $this->driver->close();
    }
}
