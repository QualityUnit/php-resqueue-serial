<?php


use ResqueSerial\Key;
use ResqueSerial\ResqueJob;
use ResqueSerial\Serial\SerialQueueImage;

/**
 * Base ResqueSerial.
 *
 * @package        ResqueSerial
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class ResqueSerial {

    const VERSION = 'resque_v1';

    /**
     * @param string $queue
     * @param \ResqueSerial\Job $serialJob
     * @param bool $monitor
     * @return string
     */
    public static function enqueue($queue, \ResqueSerial\Job $serialJob, $monitor = false) {
        $id = Resque::generateJobId();

        $serialQueue = SerialQueueImage::create($queue, $serialJob->getSerialId());

        // in case serial queue is split into multiple subqueues
        $subqueue = $serialQueue->generateSubqueueName($serialJob->getSecondarySerialId());

        $mainArgs = [
                \ResqueSerial\Task\SerialTask::ARG_SERIAL_QUEUE => $serialQueue->getQueue()
        ];

        $serialArgs = array_merge($mainArgs, [
                "jobArgs" => $serialJob->getArgs(),
                "serialId" => $serialJob->getSerialId(),
                "secondarySerialId" => $serialJob->getSecondarySerialId()
        ]);


        self::createSerialJob(
                $subqueue,
                $serialJob->getClass(),
                $serialArgs,
                $id
        );

        ResqueJob::create(
                $queue,
                \ResqueSerial\Task\SerialTaskFactory::SERIAL_CLASS,
                $mainArgs,
                $monitor,
                $id
        );

        return $id;
    }

    /**
     * @param string $subqueue
     * @param string $class
     * @param mixed[] $args
     * @param string $id
     * @return boolean success
     */
    private static function createSerialJob($subqueue, $class, array $args, $id) {
        $encodedItem = json_encode([
                'class' => $class,
                'args' => [$args],
                'id' => $id,
                'queue_time' => microtime(true),
        ]);
        if ($encodedItem === false) {
            return false;
        }

        $length = Resque::redis()->rpush(Key::serialQueue($subqueue), $encodedItem);
        if ($length < 1) {
            return false;
        }

        return true;
    }
}