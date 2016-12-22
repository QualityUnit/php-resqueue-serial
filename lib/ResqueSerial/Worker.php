<?php


namespace ResqueSerial;


use Psr\Log\LogLevel;
use Resque_Event;
use Resque_Job;
use ResqueSerial\JobStrategy\Serial;

class Worker extends \Resque_Worker {

    const MAX_SERIAL_WORKERS = 5; // FIXME: extract

    const SLEEP_SECONDS = 1;

    public function doneWorking() {
        parent::doneWorking();

        while($this->isSerialLimitReached()) {
            sleep(self::SLEEP_SECONDS);
        }
    }

    /**
     * @param Resque_Job $job
     * @return bool
     */
    private function isJobSerial(Resque_Job $job) {
        return $job->getTaskClass() === SerialTaskFactory::SERIAL_CLASS;
    }

    private function createStrategy($job) {
        if(get_class($job) === SerialTask::class) {
            return new Serial($this);
        }
        return $this->jobStrategy;
    }

    /**
     * @param $job
     */
    protected function processJob(Resque_Job $job) {
        if(!$this->isJobSerial($job)) {
            parent::processJob($job);
            return;
        }

        $lock = new Lock($job->getArguments()['serialQueue']);

        if(!$lock->acquire()) {
            $this->logger->log(LogLevel::INFO, 'Nothing to do with job {job}', array('job' => $job));
            return;
        }

        $this->logger->log(LogLevel::NOTICE, 'Starting work on {job}', array('job' => $job));
        Resque_Event::trigger('beforeFork', $job);
        $this->workingOn($job);

        $job->setTaskFactory(new SerialTaskFactory($lock));

        try {
            $this->createStrategy($job)->perform($job);
        } catch (ForkException $e) {
            $lock->release();
        }

        $this->doneWorking();
    }

    /**
     * @return boolean
     */
    private function isSerialLimitReached() {
        $count = \Resque::redis()->sCard("serial:worker:" . (string)$this . ":serial_workers");
        return $count >= self::MAX_SERIAL_WORKERS;
    }

}