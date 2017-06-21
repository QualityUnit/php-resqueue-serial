<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
use Resque\Job\Job;
use Resque\Job\Processor\IProcessor;
use Resque\Job\QueuedJob;
use Resque\Job\Reservations\IStrategy;
use Resque\Job\Reservations\TerminateException;
use Resque\Job\Reservations\WaitException;
use Resque\Job\RunningJob;
use Resque\Log;

abstract class WorkerBase {

    /** @var IJobSource */
    private $source;
    /** @var \Resque\Job\Reservations\IStrategy */
    private $reserveStrategy;
    /** @var IWorkerImage */
    private $image;

    /**
     * @param IJobSource $source
     * @param IStrategy $reserveStrategy
     * @param IWorkerImage $image
     */
    public function __construct(IJobSource $source, IStrategy $reserveStrategy, IWorkerImage $image) {
        $this->source = $source;
        $this->reserveStrategy = $reserveStrategy;
        $this->image = $image;
    }

    /**
     * @return IWorkerImage
     */
    public function getImage() {
        return $this->image;
    }

    public function work() {
        while ($this->canRun()) {
            try {
                $queuedJob = $this->reserveStrategy->reserve($this->source);
            } catch (WaitException $e) {
                Log::debug('Job not found.');
                continue;
            } catch (TerminateException $e) {
                Log::notice('Job not found. Terminating.');
                break;
            }

            Log::info("Found job {$queuedJob->getId()}. Processing.");

            $runningJob = $this->startWorkOn($queuedJob);

            try {
                $this->resolveProcessor($runningJob->getJob())->process($runningJob);
                Log::info("Processing of job {$runningJob->getId()} has finished");
            } catch (\Exception $e) {
                Log::critical("Unexpected error occurred during execution of a job. \nError: {$e->getMessage()} \nTrace: {$e->getTraceAsString()}");
            }

            $this->workDone($runningJob);
        }
    }

    protected abstract function canRun();

    /**
     * @param Job $job
     * @return IProcessor
     */
    protected abstract function resolveProcessor(Job $job);

    protected function setStrategy(IStrategy $strategy) {
        $this->reserveStrategy = $strategy;
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

        $this->image->updateState($this->getWorkerStatusData($runningJob));

        return $runningJob;
    }

    private function workDone(RunningJob $runningJob) {
        $this->image->clearState();
    }
}