<?php


namespace ResqueSerial\Serial;


use Resque;
use Resque_Stat;
use ResqueSerial\Key;

class SerialWorkerImage {

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
        return Resque::redis()->smembers(Key::serialWorkers());
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
     * @return $this
     */
    public function addToPool() {
        Resque::redis()->sadd(Key::serialWorkers(), $this->id);
        return $this;
    }

    /**
     * @return $this
     */
    public function clearParent() {
        Resque::redis()->del(Key::serialWorkerParent($this->id));
        return $this;
    }

    /**
     * @return $this
     */
    public function clearStarted() {
        Resque::redis()->del(Key::serialWorkerStart($this->id));
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
        Resque::redis()->del(Key::serialWorker($this->id));
        return $this;
    }

    /**
     * @return bool
     */
    public function exists() {
        return (bool)Resque::redis()->sismember(Key::serialWorkers(), $this->id);
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
     * @return bool|string
     */
    public function getParent() {
        return Resque::redis()->get(Key::serialWorkerParent($this->id));
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
        Resque::redis()->srem(Key::serialWorkers(), $this->id);
        return $this;
    }

    /**
     * @param string $parentId
     *
     * @return $this
     */
    public function setParent($parentId) {
        Resque::redis()->set(Key::serialWorkerParent($this->id), $parentId);
        return $this;
    }

    /**
     * @return $this
     */
    public function setStartedNow() {
        Resque::redis()->set(Key::serialWorkerStart($this->id), strftime('%a %b %d %H:%M:%S %Z %Y'));
        return $this;
    }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function setState($data) {
        Resque::redis()->set(Key::serialWorker($this->id), $data);
        return $this;
    }
}