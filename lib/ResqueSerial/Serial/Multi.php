<?php


namespace ResqueSerial\Serial;


use Resque_Event;
use RuntimeException;

class Multi implements IWorker {

    /**
     * @var string
     */
    private $queue;
    /**
     * @var
     */
    private $config;
    /**
     * @var int[]
     */
    private $children;


    /**
     * Multi constructor.
     *
     * @param string $queue
     * @param $config
     */
    public function __construct($queue, $config) {
        $this->queue = $queue;
        $this->config = $config;
    }

    function work() {
        $this->forkChildren();

        while (true) {
            foreach ($this->children as $pid) {
                $response = pcntl_waitpid($pid, $status, WNOHANG);
                if ($pid == $response) {
                    unset($this->children[$pid]);
                }
            }

            if(count($this->children) == 0) {
                break;
            }

            Resque_Event::trigger(Worker::RECOMPUTE_CONFIG_EVENT, $this);
            sleep(1);
        }
        //todo:
    }

    private function forkChildren() {
        foreach ($this->config->getQueues as $queue) {
            $this->forkSingleWorker($queue);
        }
    }

    private function forkSingleWorker($queue) {
        $childPid = $this->fork();

        // Forked and we're the child. Run the job.
        if ($childPid === 0) {
            $worker = new Single($queue, true);
            $worker->work();
            exit(0);
        }

        $this->children[] = $childPid;
    }

    private function fork() {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        return $pid;
    }
}