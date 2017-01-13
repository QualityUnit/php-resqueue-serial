<?php


namespace ResqueSerial;


use Resque;
use Resque_Stat;

class WorkerImage {

    /** @var string */
    protected $id;
    /** @var string */
    protected $hostname;
    /** @var int */
    protected $pid;
    /** @var string */
    protected $queue;
    
    /**
     * @return string[] worker ids
     */
    public static function all() {
        return Resque::redis()->smembers(Key::workers());
    }

    /**
     * Creates new worker image.
     *
     * @param $queue
     *
     * @return self
     */
    public static function create($queue) {
        $worker = new static();
        $worker->hostname = gethostname();
        $worker->pid = getmypid();
        $worker->queue = $queue;
        $worker->id = gethostname() . '~' . getmypid() . '~' . $queue;

        return $worker;
    }

    /**
     * Creates worker image from id.
     *
     * @param $workerId
     *
     * @return self
     */
    public static function fromId($workerId) {
        $parts = explode('~', $workerId, 3);

        $worker = new static();
        $worker->id = $workerId;
        $worker->hostname = @$parts[0];
        $worker->pid = @$parts[1];
        $worker->queue = @$parts[2];

        return $worker;
    }

    /**
     * @param string $serialWorkerId
     *
     * @return $this
     */
    public function addSerialWorker($serialWorkerId) {
        Resque::redis()->sadd(Key::workerSerialWorkers($this->id), $serialWorkerId);
        return $this;
    }

    /**
     * @return $this
     */
    public function addToPool() {
        Resque::redis()->sadd(Key::workers(), $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function clearSerialWorkers() {
        Resque::redis()->del(Key::workerSerialWorkers($this->id));
        return $this;
    }

    /**
     * @return $this
     */
    public function clearStarted() {
        Resque::redis()->del(Key::workerStart($this->id));
        return $this;
    }

    /**
     * @param string $stat
     *
     * @return $this
     */
    public function clearStat($stat) {
        Resque_Stat::clear("$stat:" . $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function clearState() {
        Resque::redis()->del(Key::worker($this->id));
        return $this;
    }

    /**
     * @return bool
     */
    public function exists() {
        return (bool)Resque::redis()->sismember(Key::workers(), $this->id);
    }

    /**
     * @return string
     */
    public function getHostname() {
        return $this->hostname;
    }

    /**
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getPid() {
        return $this->pid;
    }

    /**
     * @return string
     */
    public function getQueue() {
        return $this->queue;
    }

    public function getSerialWorkers() {
        return Resque::redis()->smembers(Key::workerSerialWorkers($this->id));
    }

    /**
     * @param string $stat
     *
     * @return $this
     */
    public function incStat($stat) {
        Resque_Stat::incr("$stat:" . $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function removeFromPool() {
        Resque::redis()->srem(Key::workers(), $this->id);
        return $this;
    }

    /**
     * @param string $serialWorkerId
     *
     * @return $this
     */
    public function removeSerialWorker($serialWorkerId) {
        Resque::redis()->srem(Key::workerSerialWorkers($this->id), $serialWorkerId);
        return $this;
    }

    /**
     * @return $this
     */
    public function setStartedNow() {
        Resque::redis()->set(Key::workerStart($this->id), strftime('%a %b %d %H:%M:%S %Z %Y'));
        return $this;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setState($data) {
        Resque::redis()->set(Key::worker($this->id), $data);
        return $this;
    }
}