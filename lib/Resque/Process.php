<?php

namespace Resque;

use Resque;

class Process {
    /** @var string */
    private static $prefix = null;

    /**
     * fork() helper method for php-resque that handles issues PHP socket
     * and phpredis have with passing around sockets between child/parent
     * processes.
     *
     * Will close connection to Redis before forking.
     *
     * @return int Return vars as per pcntl_fork(). False if pcntl_fork is unavailable
     */
    public static function fork() {
        if (!function_exists('pcntl_fork')) {
            return false;
        }

        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new \RuntimeException('Unable to fork child worker.');
        }

        if ($pid === 0) {
            // Close the connection to Redis after forking. This is a workaround for issues phpredis has.
            Resque::resetRedis();
        }

        return $pid;
    }

    public static function setTitle($title) {
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title(self::getTitlePrefix() . ": $title");
        } else if (function_exists('setproctitle')) {
            setproctitle(self::getTitlePrefix() . ": $title");
        }
    }

    public static function setTitlePrefix($prefix) {
        self::$prefix = $prefix;
    }

    private static function getTitlePrefix() {
        return \Resque::VERSION_PREFIX . '-' . (self::$prefix ? self::$prefix : 'unset');
    }

    /**
     * Wait until the child process finishes before continuing
     * @param int $pid
     * @return int
     */
    public static function waitForPid($pid) {
        pcntl_waitpid($pid, $status);
        return pcntl_wexitstatus($status);
    }
}