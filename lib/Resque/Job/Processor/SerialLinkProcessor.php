<?php


namespace Resque\Job\Processor;


use Resque\Config\GlobalConfig;
use Resque\Config\WorkerConfig;
use Resque\Exception;
use Resque\Job\RunningJob;
use Resque\Job\SerialJobLink;
use Resque\Log;
use Resque\Process;
use Resque\Queue\QueueLock;
use Resque\SignalHandler;
use Resque\Worker\Serial\SerialWorker;
use Resque\Worker\Serial\SerialWorkerImage;
use Resque\Worker\WorkerImage;

class SerialLinkProcessor implements IProcessor {

    /** @var WorkerImage */
    private $workerImage;

    /**
     * @param WorkerImage $workerImage
     */
    public function __construct(WorkerImage $workerImage) {
        $this->workerImage = $workerImage;
    }

    public function process(RunningJob $runningJob) {
        $serialQueue = SerialJobLink::getSerialQueue($runningJob->getJob());
        if ($serialQueue == null) {
            Log::critical('Serial link does not contain information about serial queue. '
                    . json_encode($runningJob->getJob()->toArray()));
            return;
        }
        $lock = new QueueLock($serialQueue);

        if(!$lock->acquire()) {
            return;
        }

        try {
            $pid = Process::fork();
        } catch (Exception $e) {
            Log::critical("Fork to start serial worker failed.");
            return;
        }

        if ($pid === 0) {
            $this->spawnSerialProcess($lock, SerialWorkerImage::create($serialQueue));
            exit(0);
        } else {
            Process::waitForPid($pid); // wait until worker spawned

            $workerConfig = GlobalConfig::getInstance()->getWorkerConfig($this->workerImage->getQueue());
            if (!$workerConfig) {
                Log::critical("Can't find config for queue '{$this->workerImage->getQueue()}'.");
                return;
            }
            while($this->serialWorkerLimitReached($workerConfig)) {
                sleep(3);
            }
        }
    }

    private function spawnSerialProcess(QueueLock $lock, SerialWorkerImage $image) {
        SignalHandler::instance()->unregisterAll()->register(SIGCHLD, SIG_IGN);

        $this->workerImage->addSerialWorker($image->getId());
        try {
            $pid = Process::fork();
        } catch (Exception $e) {
            Log::critical("Fork to spawn {$image->getId()} failed.");
            $this->workerImage->removeSerialWorker($image->getId());
            return;
        }

        if ($pid === 0) {
            $serialWorker = new SerialWorker($image, $lock);
            $serialWorker->work($this->workerImage->getId());
            $this->workerImage->removeSerialWorker($image->getId());
            exit(0);
        }
    }

    private function serialWorkerLimitReached(WorkerConfig $config) {
        return count($this->workerImage->getSerialWorkers()) >= $config->getMaxSerialWorkers();
    }
}