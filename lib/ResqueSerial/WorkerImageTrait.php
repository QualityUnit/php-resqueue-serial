<?php


namespace ResqueSerial;


trait WorkerImageTrait {

    /** @var string */
    protected $id;
    /** @var string */
    protected $hostname;
    /** @var int */
    protected $pid;
    /** @var string */
    protected $queue;

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
     * @param string $stat
     *
     * @return $this
     */
    public function clearStat($stat) {
        Stats::clear("$stat:" . $this->id);
        return $this;
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

    /**
     * @param string $stat
     *
     * @return $this
     */
    public function incStat($stat) {
        Stats::incr("$stat:" . $this->id);
        return $this;
    }

    /**
     * @return bool true if process with workers PID exists on this machine
     */
    public function isAlive() {
        return posix_getpgid($this->getPid()) > 0;
    }

    /**
     * @return bool true if worker belongs to this machine
     */
    public function isLocal() {
        return gethostname() == $this->getHostname();
    }
}