<?php

/**
 * Base ResqueSerial.
 *
 * @package        ResqueSerial
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class ResqueSerial {

    /**
     * @param $queue
     * @param \ResqueSerial\Job $serialJob
     * @param bool $monitor
     * @return string
     */
    public static function enqueue($queue, \ResqueSerial\Job $serialJob, $monitor = false) {
        $id = Resque::generateJobId();

        $serialQueue = self::generateSerialQueueName($queue, $serialJob);
        $mainArgs = [
                "serialQueue" => $serialQueue
        ];

        $serialArgs = array_merge($mainArgs, [
                "jobArgs" => $serialJob->getArgs(),
                "serialId" => $serialJob->getSerialId(),
                "secondarySerialId" => $serialJob->getSecondarySerialId()
        ]);


        self::createSerialJob(
                $serialQueue,
                $serialJob->getClass(),
                $serialArgs,
                $id
        );

        Resque_Job::create(
                $queue,
                \ResqueSerial\SerialJobMeta::class,
                $mainArgs,
                $monitor,
                $id
        );

        return $id;
    }

    /**
     * @param string $queue
     * @param string $class
     * @param mixed[] $args
     * @param string $id
     * @return boolean success
     */
    private static function createSerialJob($queue, $class, array $args, $id) {
        $encodedItem = json_encode([
                'class' => $class,
                'args' => $args,
                'id' => $id,
                'queue_time' => microtime(true),
        ]);
        if ($encodedItem === false) {
            return false;
        }

        $length = Resque::redis()->rpush('serial_queue:' . $queue, $encodedItem);
        if ($length < 1) {
            return false;
        }

        return true;
    }

    private static function generateSerialQueueName($queue, \ResqueSerial\Job $serialJob) {
        return $queue . '_' . $serialJob->getSerialId();
    }
}