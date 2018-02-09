<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
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

    public function __construct(IJobSource $source, WorkerImage $image) {
        parent::__construct("w-{$image->getPoolName()}-{$image->getCode()}", $image);
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

    /**
     * @throws \Resque\Api\RedisError
     */
    protected function doWork() {
        $queuedJob = $this->source->bufferNextJob();

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

        $bufferedJob = $this->source->bufferPop();
        if ($bufferedJob === null) {
            throw new \RuntimeException('Buffer is empty after processing.', [
                'payload' => $queuedJob->toString()
            ]);
        }
        $this->validateJob($queuedJob, $bufferedJob);
    }

    protected function prepareWork() {
        // NOOP
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return RunningJob
     * @throws \Resque\Api\RedisError
     */
    private function startWorkOn(QueuedJob $queuedJob) {
        return new RunningJob($this, $queuedJob);
    }

    /**
     * @param QueuedJob $expected
     * @param QueuedJob $actual
     */
    private function validateJob(QueuedJob $expected, QueuedJob $actual) {
        if ($expected->getId() !== $actual->getId()) {
            Log::critical('Dequeued job does not match buffered job.', [
                'payload' => $expected->toString(),
                'actual' => $actual->toString()
            ]);
            exit(0);
        }
    }
}