<?php


namespace ResqueSerial;


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
        return Key::serial('queue', $queue, 'lock');
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function serialCompletedCount($queue) {
        return Key::serial('queue', $queue, 'completed_count');
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
        return Key::serial('queue', $queue, 'config');
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

    private static function serial(...$parts) {
        return self::of('serial', ...$parts);
    }
}