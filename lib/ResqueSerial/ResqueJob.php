<?php

namespace ResqueSerial;

use InvalidArgumentException;
use Resque;
use ResqueSerial\Exception\BaseException;
use ResqueSerial\Job\DontPerformException;
use ResqueSerial\Job\Status;
use ResqueSerial\Task\ITask;
use ResqueSerial\Task\ITaskFactory;
use ResqueSerial\Task\TaskFactory;

/**
 * Resque job.
 *
 * @package        Resque/Job
 * @author        Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class ResqueJob {
    /**
     * @var string The name of the queue that this job belongs to.
     */
    public $queue;

    /**
     * @var DeprecatedWorker Instance of the Resque worker running this job.
     */
    public $worker;

    /**
     * @var array Array containing details of the job.
     */
    public $payload;

    /**
     * @var object|ITask Instance of the class performing work for this job.
     */
    private $instance;

    /**
     * @var ITaskFactory
     */
    private $taskFactory;

    /**
     * Instantiate a new instance of a job.
     *
     * @param string $queue The queue that the job belongs to.
     * @param array $payload array containing details of the job.
     */
    public function __construct($queue, $payload) {
        $this->queue = $queue;
        $this->payload = $payload;
    }

    /**
     * Create a new job and save it to the specified queue.
     *
     * @param string $queue The name of the queue to place the job in.
     * @param string $class The name of the class that contains the code to execute the job.
     * @param array $args Any optional arguments that should be passed when the job is executed.
     * @param boolean $monitor Set to true to be able to monitor the status of a job.
     * @param string $id Unique identifier for tracking the job. Generated if not supplied.
     * @param int $fails Number of times the job failed previously
     *
     * @return string
     */
    public static function create($queue, $class, $args = null, $monitor = false, $id = null,
            $fails = 0) {
        if (is_null($id)) {
            $id = Resque::generateJobId();
        }

        if ($args !== null && !is_array($args)) {
            throw new InvalidArgumentException(
                    'Supplied $args must be an array.'
            );
        }
        Resque::push($queue, array(
                'class' => $class,
                'args' => array($args),
                'id' => $id,
                'queue_time' => microtime(true),
                'fails' => $fails
        ));

        if ($monitor) {
            Status::create($id);
        }

        return $id;
    }

    public function getTaskClass() {
        return $this->payload['class'];
    }

    /**
     * Update the status of the current job.
     *
     * @param int $status Status constant from \ResqueSerial\Job\Status indicating the current status of a job.
     */
    public function updateStatus($status) {
        if (empty($this->payload['id'])) {
            return;
        }

        $statusInstance = new Status($this->payload['id']);
        $statusInstance->update($status);
    }

    /**
     * Return the status of the current job.
     *
     * @return int The status of the job as one of the \ResqueSerial\Job\Status constants.
     */
    public function getStatus() {
        $status = new Status($this->payload['id']);

        return $status->get();
    }

    /**
     * Get the arguments supplied to this job.
     *
     * @return array Array of arguments.
     */
    public function getArguments() {
        if (!isset($this->payload['args'])) {
            return array();
        }

        return @$this->payload['args'][0];
    }

    /**
     * Get the number of times this job failed previously
     *
     * @return int number of previous failures
     */
    public function getFails() {
        return isset($this->payload['fails']) ? (int)$this->payload['fails'] : 0;
    }

    /**
     * Returns queue time as micro time float.
     *
     * @return float micro time float
     */
    public function getQueueTime() {
        return isset($this->payload['queue_time']) ? (int)$this->payload['queue_time'] : 0;
    }

    /**
     * Get the instantiated object for this job that will be performing work.
     *
     * @return ITask Instance of the object that this job belongs to.
     * @throws BaseException
     */
    public function getInstance() {
        if (!is_null($this->instance)) {
            return $this->instance;
        }

        $this->instance = $this->getTaskFactory()
                ->create($this->payload['class'], $this->getArguments(), $this->queue);
        $this->instance->job = $this;

        return $this->instance;
    }

    /**
     * Actually execute a job by calling the perform method on the class
     * associated with the job with the supplied arguments.
     *
     * @return bool
     * @throws BaseException When the job's class could not be found or it does not contain a perform method.
     */
    public function perform() {
        try {
            EventBus::trigger('beforePerform', $this);

            $instance = $this->getInstance();
            if (method_exists($instance, 'setUp')) {
                $instance->setUp();
            }

            $instance->perform();

            if (method_exists($instance, 'tearDown')) {
                $instance->tearDown();
            }

            EventBus::trigger('afterPerform', $this);
        } // beforePerform/setUp have said don't perform this job. Return.
        catch (DontPerformException $e) {
            return false;
        }

        return true;
    }

    /**
     * Mark the current job as having failed.
     *
     * @param $exception
     */
    public function fail($exception) {
        EventBus::trigger('onFailure', array(
                'exception' => $exception,
                'job' => $this,
        ));

        $this->updateStatus(Status::STATUS_FAILED);
        Failure::create(
                $this->payload,
                $exception,
                $this->worker,
                $this->queue
        );
    }

    /**
     * Re-queue the current job.
     *
     * @param bool $increaseFails true if fails should be increased
     *
     * @return string
     */
    public function recreate($increaseFails = true) {
        $status = new Status($this->payload['id']);
        $monitor = false;
        if ($status->isTracking()) {
            $monitor = true;
        }

        $newFails = $this->getFails();
        if ($increaseFails) {
            $newFails++;
        }

        return self::create($this->queue, $this->payload['class'], $this->getArguments(), $monitor, null, $newFails);
    }

    /**
     * Generate a string representation used to describe the current job.
     *
     * @return string The string representation of the job.
     */
    public function __toString() {
        $name = array(
                'Job{' . $this->queue . '}'
        );
        if (!empty($this->payload['id'])) {
            $name[] = 'ID: ' . $this->payload['id'];
        }
        $name[] = $this->payload['class'];
        if (!empty($this->payload['args'])) {
            $name[] = json_encode($this->payload['args']);
        }

        return '(' . implode(' | ', $name) . ')';
    }

    /**
     * @param ITaskFactory $taskFactory
     *
     * @return ResqueJob
     */
    public function setTaskFactory(ITaskFactory $taskFactory) {
        $this->taskFactory = $taskFactory;

        return $this;
    }

    /**
     * @return ITaskFactory
     */
    public function getTaskFactory() {
        if ($this->taskFactory === null) {
            $this->taskFactory = new TaskFactory();
        }

        return $this->taskFactory;
    }
}
