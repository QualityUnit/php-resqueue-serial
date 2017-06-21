<?php

namespace Resque;


use Resque;


class Stats {

    /**
     * Increment the value of the specified global statistic by a certain amount (default is 1)
     *
     * @param string $stat The name of the statistic to increment.
     * @param int $by The amount to increment the statistic by.
     *
     * @return boolean True if successful, false if not.
     */
    public static function incGlobal($stat, $by = 1) {
        return (bool)Resque::redis()->incrby(Key::statsGlobal($stat), $by);
    }

    /**
     * Increment the value of the specified queue statistic by a certain amount (default is 1)
     *
     * @param string $queue
     * @param string $stat The name of the statistic to increment.
     * @param int $by The amount to increment the statistic by.
     *
     * @return bool True if successful, false if not.
     */
    public static function incQueue($queue, $stat, $by = 1) {
        return (bool)Resque::redis()->incrby(Key::statsQueue($queue, $stat), $by);
    }

    /**
     * Delete a statistic with the given name.
     *
     * @param string $stat The name of the statistic to delete.
     *
     * @return boolean True if successful, false if not.
     */
    public static function clearGlobal($stat) {
        return (bool)Resque::redis()->del(Key::statsGlobal($stat));
    }
}