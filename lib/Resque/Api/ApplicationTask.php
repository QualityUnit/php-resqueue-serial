<?php


namespace Resque\Api;


use Resque\Task\ApplicationJobWrapper;

/**
 * Class ApplicationTask provides api to create remotely defined resque jobs.
 *
 * Custom tasks must implement following static methods:
 *
 *     getPerformer(array $args) -> Performer
 *     getRetryPerformer(array $args) -> Performer
 *       - called only if same job failed previously and was re-queued as a result
 *
 * Performer must implement following instance methods:
 *
 *     perform() -> void
 *
 * Performer's method perform() may throw following ApplicationTaskExceptions:
 *
 *     \Resque\Api\FailApplicationTaskException -> reports job as failed without any attempts to retry
 *     \Resque\Api\RescheduleApplicationTaskException -> reports job as successful and reschedules accordingly
 *     any other ApplicationTaskException -> reports job as failed and re-queues it again if retry limit
 *                            wasn't reached, otherwise reports it as failed
 *
 *
 * @package Resque\Api
 */
final class ApplicationTask {

    // task class methods (static)
    const PERFORMER_GETTER = 'getPerformer';
    const RETRY_PERFORMER_GETTER = 'getRetryPerformer';
    // performer methods
    const PERFORM_METHOD = 'perform';

    /**
     * @param $performer
     *
     * @throws ApplicationTaskException
     */
    public static function checkPerformer($performer) {
        if (!method_exists($performer, self::PERFORM_METHOD)) {
            throw new ApplicationTaskException("Performer $performer does not contain a perform method.");
        }
    }

    /**
     * @param $taskClass
     *
     * @throws ApplicationTaskException
     */
    public static function checkTaskClass($taskClass) {
        if (!class_exists($taskClass)) {
            throw new ApplicationTaskException("Could not find application task class $taskClass.");
        }
        if (!method_exists($taskClass, self::PERFORMER_GETTER)) {
            throw new ApplicationTaskException("Task class $taskClass does not contain a performer getter.");
        }
        if (!method_exists($taskClass, self::RETRY_PERFORMER_GETTER)) {
            throw new ApplicationTaskException("Task class $taskClass does not contain a retry performer getter.");
        }
    }

    /**
     * Creates application task job from provided job. Class returned by the job must meet criteria
     * described by docs in this class, and validated by its ::checkTaskClass() method.
     *
     * @param JobDescriptor $job
     * @param ApplicationEnvironment $environment
     *
     * @return JobDescriptor wrapped application task
     * @throws ApplicationTaskException if class specified in job is not a valid task class
     */
    public static function create(JobDescriptor $job, ApplicationEnvironment $environment) {
        self::checkTaskClass($job->getClass());

        return new ApplicationJobWrapper($job, $environment);
    }
}