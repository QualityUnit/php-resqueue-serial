<?php


namespace Resque;


use Resque\Config\GlobalConfig;

class Key {

    public static function batchAllocationFailures() {
        return self::of('batch', 'allocation_failures');
    }

    public static function committedBatch($id) {
        return self::of('committed', $id);
    }

    public static function committedBatchList() {
        return self::of('committed');
    }

    public static function batchPoolSourceWorker($poolName) {
        return self::of('pool', $poolName);
    }

    public static function batchPoolBacklogList($poolName, $sourceId) {
        return self::of('pool', $poolName, 'backlog', $sourceId);
    }

    public static function batchPoolUnitQueueSet($poolName, $unitId) {
        return self::of('pool', $poolName, $unitId, 'queues');
    }

    public static function batchPoolQueuesSortedSet($poolName) {
        return self::of('pool', $poolName, 'unit_queues');
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

    public static function jobAllocationFailures() {
        return self::of('job', 'allocation_failures');
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
    public static function localAllocatorBuffer($allocatorNumber) {
        return self::of('allocator', GlobalConfig::getInstance()->getNodeId(), $allocatorNumber);
    }

    /**
     * @return string
     */
    public static function localAllocatorProcesses() {
        return self::of('process', GlobalConfig::getInstance()->getNodeId(), 'allocator');
    }

    /**
     * @return string
     */
    public static function localSchedulerPid() {
        return self::of('scheduler_pid', GlobalConfig::getInstance()->getNodeId());
    }

    /**
     * @return string
     */
    public static function localSchedulerProcesses() {
        return self::of('workers', GlobalConfig::getInstance()->getNodeId(), 'scheduler');
    }

    public static function localStaticPoolProcesses($poolName) {
        return self::of('process', GlobalConfig::getInstance()->getNodeId(), 'static_pool', $poolName);
    }

    public static function localBatchPoolProcesses($poolName) {
        return self::of('process', GlobalConfig::getInstance()->getNodeId(), 'batch_pool', $poolName);
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
        return self::of('queue', $name);
    }

    /**
     * @param string $queue
     *
     * @return string
     */
    public static function queueLock($queue) {
        return self::of('queuedata', $queue, 'lock');
    }

    /**
     * @return string
     */
    public static function queues() {
        return 'queues';
    }

    public static function staticPoolQueue($poolName) {
        return self::of('static', 'queue', $poolName);
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
        return self::of('queuestat', GlobalConfig::getInstance()->getNodeId(), $stat, $queue);
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
        return self::of('worker', $worker);
    }

    /**
     * @param string $worker
     *
     * @return string
     */
    public static function workerStart($worker) {
        return self::of('worker', $worker, 'started');
    }

    /**
     * @return string
     */
    public static function workers() {
        return self::of('workers');
    }

    private static function of(...$parts) {
        return implode(':', $parts);
    }
}