<?php


namespace Resque\Worker;


use Resque\Job\IJobSource;
use Resque\Job\Processor\StandardProcessor;
use Resque\Job\QueuedJob;
use Resque\Job\RunningJob;
use Resque\Log;
use Resque\Process\AbstractProcess;
use Resque\Protocol\DeferredException;
use Resque\Protocol\DiscardedException;
use Resque\Protocol\Job;
use Resque\Protocol\UniqueLock;
use Resque\Stats\JobStats;

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

        if (!$this->lockJob($queuedJob->getJob())) {
            return;
        }

        $runningJob = $this->startWorkOn($queuedJob);

        try {
            $this->processor->process($runningJob);
            Log::debug("Processing of job {$runningJob->getId()} has finished", [
                'payload' => $runningJob->getJob()->toString()
            ]);
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
        $bufferedJob = $this->source->getBuffer()->popJob();
        if ($bufferedJob === null) {
            Log::error('Buffer is empty after processing.', [
                'payload' => $queuedJob->toString()
            ]);
            throw new \RuntimeException('Invalid state.');
        }
        $this->assertJobsEqual($queuedJob, $bufferedJob);

        $this->getImage()->clearRuntimeInfo();
    }

    private function lockJob(Job $job) {
        $uid = $job->getUid();

        if ($uid === null) {
            return true;
        }

        try {
            UniqueLock::lock(
                $uid->getId(),
                $this->source->getBuffer()->getKey(),
                $uid->isDeferrable()
            );

            return true;
        } catch (DeferredException $e) {
            JobStats::getInstance()->reportUniqueDeferred();
        } catch (DiscardedException $e) {
            JobStats::getInstance()->reportUniqueDiscarded();
        }

        return false;
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