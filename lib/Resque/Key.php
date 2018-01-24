<?php


namespace Resque;


class Key {

    public static function committedBatch($id) {
        return self::of('committed', $id);
    }

    public static function committedBatchList() {
        return self::of('committed');
    }

    /**
     * @param int $at
     *
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
     * @param string $id
     *
     * @return string
     */
    public static function jobStatus($id) {
        return self::of('job', $id, 'status');
    }

    /**
     * @param string $allocatorNumber
     *
     * @return string
     */
    public static function localBatchAllocatorBuffer($allocatorNumber) {
        return Key::of('allocator', gethostname(), 'batch', $allocatorNumber);
    }

    /**
     * @return string
     */
    public static function localBatchAllocatorProcesses() {
        return Key::of('process', gethostname(), 'allocator', 'batch');
    }

    /**
     * @param string $allocatorNumber
     *
     * @return string
     */
    public static function localJobAllocatorBuffer($allocatorNumber) {
        return Key::of('allocator', gethostname(), 'job', $allocatorNumber);
    }

    /**
     * @return string
     */
    public static function localJobAllocatorProcesses() {
        return Key::of('process', gethostname(), 'allocator', 'job');
    }

    /**
     * @return string
     */
    public static function localSchedulerPid() {
        return Key::of('scheduler_pid', gethostname());
    }

    /**
     * @return string
     */
    public static function localSchedulerProcesses() {
        return Key::of('workers', gethostname(), 'scheduler');
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public static function plan($id) {
        return self::of('plan', $id);
    }

    public static function planSchedule() {
        return self::of('plan_schedule');
    }

    /**
     * @param int $timestamp
     *
     * @return string
     */
    public static function planTimestamp($timestamp) {
        return self::of('plan_schedule', $timestamp);
    }

    /**
     * @param string $name
     *
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
    public static function queueLock($queue) {
        return Key::of('queuedata', $queue, 'lock');
    }

    /**
     * @return string
     */
    public static function queues() {
        return 'queues';
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

    public static function unassigned() {
        return self::of('unassigned');
    }

    public static function uniqueDeferred($uniqueId) {
        return self::of('unique', $uniqueId, 'deferred');
    }

    public static function uniqueState($uniqueId) {
        return self::of('unique', $uniqueId, 'state');
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function worker($worker) {
        return Key::of('worker', $worker);
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function workerStart($worker) {
        return Key::of('worker', $worker, 'started');
    }

    /**
     * @return string
     */
    public static function workers() {
        return Key::of('workers');
    }

    private static function of(...$parts) {
        return implode(':', $parts);
    }
}