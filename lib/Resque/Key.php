<?php


namespace Resque;


class Key {

    public static function of(...$parts) {
        return implode(':', $parts);
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function queueLock($queue) {
        return Key::serial('queuedata', $queue, 'lock');
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function serialCompletedCount($queue) {
        return Key::serial('queuedata', $queue, 'completed_count');
    }

    /**
     * @param string $name
     * @return string
     */
    public static function queue($name) {
        return Key::of('queue', $name);
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function serialQueue($queue) {
        return Key::serial('queue', $queue);
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function serialQueueConfig($queue) {
        return Key::serial('queuedata', $queue, 'config');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function serialWorker($worker) {
        return Key::serial('serial_worker', $worker);
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function serialWorkerParent($worker) {
        return Key::serial('serial_worker', $worker, 'parent');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function serialWorkerRunners($worker) {
        return Key::serial('serial_worker', $worker, 'runners');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function serialWorkerStart($worker) {
        return Key::serial('serial_worker', $worker, 'started');
    }

    /**
     * @return string
     */
    public static function serialWorkers() {
        return Key::serial('serial_workers');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function worker($worker) {
        return Key::serial('worker', $worker);
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function workerSerialWorkers($worker) {
        return Key::serial('worker', $worker, 'serial_workers');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function workerStart($worker) {
        return Key::serial('worker', $worker, 'started');
    }

    /**
     * @return string
     */
    public static function workers() {
        return Key::serial('workers');
    }

    /**
     * @return string
     */
    public static function localSchedulerPid() {
        return Key::of('scheduler_pid', gethostname());
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function jobStatus($id) {
        return self::of('job', $id, 'status');
    }

    /**
     * @param string $stat
     *
     * @return string
     */
    public static function statsGlobal($stat) {
        return self::of('stat', $stat);
    }

    /**
     * @param string $queue
     * @param string $stat
     *
     * @return string
     */
    public static function statsQueue($queue, $stat) {
        return self::of('queuestat', gethostname(), $stat, $queue);
    }

    /**
     * @param int $at
     * @return string
     */
    public static function delayed($at) {
        return self::of('delayed', $at);
    }

    /**
     * @return string
     */
    public static function delayedQueueSchedule() {
        return 'delayed_queue_schedule';
    }

    /**
     * @return string
     */
    public static function queues() {
        return 'queues';
    }

    private static function serial(...$parts) {
        return self::of('serial', ...$parts);
    }
}