<?php


namespace ResqueSerial\Serial;


use Psr\Log\LoggerInterface;
use Resque;
use ResqueSerial\Key;
use ResqueSerial\Log;
use ResqueSerial\QueueLock;

class Multi implements IWorker {

    /** @var string */
    private $queue;
    /** @var ConfigManager */
    private $config;
    /** @var int[] */
    private $children = [];
    /** @var SerialWorkerImage */
    private $image;
    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $stopping = false;
    /** @var QueueLock */
    private $lock;

    /**
     * Multi constructor.
     *
     * @param string $queue
     * @param ConfigManager $config
     * @param QueueLock $lock
     */
    public function __construct($queue, $config, QueueLock $lock) {
        $this->queue = $queue;
        $this->config = $config;
        $this->image = SerialWorkerImage::create($queue);
        $this->logger = Log::prefix(getmypid() . "-multi-$queue");
        $this->lock = $lock;
    }

    /**
     * @param SerialWorkerImage $image
     */
    public function setImage(SerialWorkerImage $image) {
        $this->image = $image;
    }

    public function shutdown() {
        $this->stopping = true;
        $this->logger->notice('Shutting down.');

        foreach ($this->children as $child) {
            posix_kill($child, SIGTERM);
        }
    }

    /**
     * On supported systems (with the PECL proctitle module installed), update
     * the name of the currently running process to indicate the current state
     * of a worker.
     *
     * @param string $status The updated process title.
     */
    public function updateProcLine($status)
    {
        $processTitle = "resque-serial-serial_worker: $status";
        if(function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title($processTitle);
        }
        else if(function_exists('setproctitle')) {
            setproctitle($processTitle);
        }
    }

    function work() {
        if (!$this->lock->acquire(5000)) {

        }
        if($this->stopping) {
            return;
        }

        $this->logger->notice("Starting.");
        $this->image->addToPool()->setStartedNow();

        $this->updateProcLine('Forking runners');
        $this->forkChildren();

        $this->updateProcLine('Managing runners');
        while (true) {
            foreach ($this->children as $pid) {
                $response = pcntl_waitpid($pid, $status, WNOHANG);
                if ($pid == $response) {
                    unset($this->children[$pid]);
                }
            }

            if (count($this->children) == 0) {
                break;
            }

            pcntl_signal_dispatch();

            \Resque_Event::trigger(SerialWorker::RECOMPUTE_CONFIG_EVENT, $this);
            sleep(1);
        }

        $this->image->removeFromPool()->clearStarted();
        $this->logger->notice("Ended.");
    }

    private function forkChildren() {
        // check if some workers already exist TODO
//        $workers = \Resque::redis()->smembers('queues');
//        $existing_workers = [];
//        if (is_array($workers)) {
//            foreach ($workers as $workerId) {
//                /** @noinspection PhpUnusedLocalVariableInspection */
//                list($hostname, $pid, $que) = explode(':', $workerId, 3);
//                if (!posix_getpgid($pid)) {
//                    continue;
//                }
//                $this->children[$pid] = $pid;
//                $existing_workers[] = $que;
//            }
//        }

        $currentConfig = $this->config->getCurrent();
        $queueCount = $currentConfig->getQueueCount();
        for ($i = 0; $i < $queueCount; $i++) {
            $queue = $this->queue . $currentConfig->getQueuePostfix($i);
//            if (!in_array($this->queue, $existing_workers)) {
                $this->forkSingleWorker($queue);
//            }
        }
    }

    private function forkSingleWorker($queue) {
        $childPid = Resque::fork();

        // Forked and we're the child. Run the job.
        if ($childPid === 0) {
            $worker = new Single($queue, true);
            $id = (string)$worker;
            Resque::redis()->sadd(Key::serialWorkerRunners($this->image->getId()), $id);
            $worker->work();
            Resque::redis()->srem(Key::serialWorkerRunners($this->image->getId()), $id);
            exit(0);
        }

        $this->children[$childPid] = $childPid;
    }
}