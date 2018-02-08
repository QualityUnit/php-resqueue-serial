<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
use Resque\Job\QueuedJob;
use Resque\Job\Reservations\JobUnavailableException;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Maintenance\WorkerImage;
use Resque\Process\AbstractProcess;

class WorkerProcess extends AbstractProcess {

    /**
     * @var IJobSource
     */
    private $source;

    public function __construct(string $title, IJobSource $source, WorkerImage $image) {
        parent::__construct($title, $image);
        $this->source = $source;
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
            $this->resolveProcessor($runningJob->getJob())->process($runningJob);
            Log::info("Processing of job {$runningJob->getId()} has finished");
        } catch (\Exception $e) {
            Log::critical('Unexpected error occurred during execution of a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
        }

        $this->workDone($runningJob);
    }

    protected function prepareWork() {
        // TODO: Implement prepareWork() method.
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
     */
    private function startWorkOn(QueuedJob $queuedJob) {
        $runningJob = new RunningJob($this, $queuedJob);

        $this->getImage()->updateState($this->getWorkerStatusData($runningJob));

        return $runningJob;
    }

    private function workDone(RunningJob $runningJob) {
        $this->getImage()->clearState();
    }
}