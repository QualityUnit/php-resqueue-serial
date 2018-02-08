<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
use Resque\Job\JobUnavailableException;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\QueuedJob;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process\AbstractProcess;

class WorkerProcess extends AbstractProcess {

    /** @var IJobSource */
    private $source;
    /** @var StandardProcessor */
    private $processor;

    public function __construct(string $title, IJobSource $source, WorkerImage $image) {
        parent::__construct($title, $image);
        $this->source = $source;
        $this->processor = new StandardProcessor();
    }

    /**
     * @return WorkerImage
     */
    public function getImage() {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return parent::getImage();
    }

    protected function doWork() {
        try {
            $queuedJob = $this->source->getNextJob();
        } catch (JobUnavailableException $e) {
            Log::notice('Job not found. Terminating.', [
                'exception' => $e
            ]);
            $this->shutDown();
            return;
        }

        if ($queuedJob === null) {
            Log::debug('Job not found.');
            return;
        }

        Log::info("Found job {$queuedJob->getId()}. Processing.");

        $runningJob = $this->startWorkOn($queuedJob);

        try {
            $this->processor->process($runningJob);
            Log::info("Processing of job {$runningJob->getId()} has finished", [
                'payload' => $runningJob->getJob()->toString()
            ]);
        } catch (\Exception $e) {
            Log::critical('Unexpected error occurred during execution of a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
        }

        $this->getImage()->clearState();
    }

    protected function prepareWork() {
        // NOOP
    }

    private function getWorkerStatusData(RunningJob $runningJob) {
        return json_encode(array(
            'run_at' => strftime('%a %b %d %H:%M:%S %Z %Y', $runningJob->getStartTime()),
            'payload' => $runningJob->getJob()->toArray()
        ));
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return RunningJob
     * @throws \Resque\Api\RedisError
     */
    private function startWorkOn(QueuedJob $queuedJob) {
        $runningJob = new RunningJob($this, $queuedJob);

        $this->getImage()->updateState($this->getWorkerStatusData($runningJob));

        return $runningJob;
    }
}