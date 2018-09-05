<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\QueuedJob;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Stats\JobStats;
use Resque\Stats\PoolStats;

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
     * @throws \Resque\RedisError
     */
    protected function doWork() {
        $queuedJob = $this->source->bufferNextJob();

        if ($queuedJob === null) {
            return;
        }

        Log::debug("Found job {$queuedJob->getId()}. Processing.");

        $runningJob = $this->startWorkOn($queuedJob);

        try {
            $this->processor->process($runningJob);
            Log::debug("Processing of job {$runningJob->getId()} has finished", [
                'payload' => $runningJob->getJob()->toString()
            ]);

            PoolStats::getInstance()->reportProcessed($this->getImage()->getPoolName());
        } catch (\Exception $e) {
            Log::critical('Unexpected error occurred during execution of a job.', [
                'exception' => $e,
                'payload' => $runningJob->getJob()->toArray()
            ]);
        }

        $this->finishWorkOn($queuedJob);
    }

    protected function prepareWork() {
        // NOOP
    }

    /**
     * @param QueuedJob $expected
     * @param QueuedJob $actual
     */
    private function assertJobsEqual(QueuedJob $expected, QueuedJob $actual) {
        if ($expected->getId() === $actual->getId()) {
            return;
        }

        Log::critical('Dequeued job does not match buffered job.', [
            'payload' => $expected->toString(),
            'actual' => $actual->toString()
        ]);
        exit(0);
    }

    private function finishWorkOn(QueuedJob $queuedJob) {
        $bufferedJob = $this->source->bufferPop();
        if ($bufferedJob === null) {
            Log::error('Buffer is empty after processing.', [
                'payload' => $queuedJob->toString()
            ]);
            throw new \RuntimeException('Invalid state.');
        }
        $this->assertJobsEqual($queuedJob, $bufferedJob);

        $this->getImage()->clearRuntimeInfo();
    }

    /**
     * @param QueuedJob $queuedJob
     *
     * @return RunningJob
     * @throws \Resque\RedisError
     */
    private function startWorkOn(QueuedJob $queuedJob) {
        $this->getImage()->setRuntimeInfo(
            microtime(true),
            $queuedJob->getJob()->getName(),
            $queuedJob->getJob()->getUniqueId()
        );
        $runningJob = new RunningJob($this, $queuedJob);
        JobStats::getInstance()->reportJobProcessing($runningJob);

        return $runningJob;
    }
}