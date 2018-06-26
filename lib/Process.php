<?php

namespace Resque;

class Process {
    /** @var string */
    private static $prefix = null;

    /**
     * fork() helper method for php-resque that handles issues PHP socket
     * and phpredis have with passing around sockets between child/parent
     * processes.
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

    /**
     * @param int $pid
     *
     * @return bool
     */
    public static function isPidAlive($pid) {
        return pcntl_waitpid($pid, $status, WNOHANG) > 0;
    }

    public static function setTitle($title) {
        if (function_exists('cli_set_process_title') && PHP_OS !== 'Darwin') {
            cli_set_process_title(self::getTitlePrefix() . ": $title");
        } elseif (function_exists('setproctitle')) {
            setproctitle(self::getTitlePrefix() . ": $title");
        }
    }

    public static function setTitlePrefix($prefix) {
        self::$prefix = $prefix;
    }

    public static function signal($signal, $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) {
            Log::error("There was an attempt to send signal $signal to pid $pid");

            return;
        }

        posix_kill($pid, $signal);
    }

    /**
     * Wait until the child process finishes before continuing
     *
     * @param int $pid
     *
     * @return int
     */
    public static function waitForPid($pid) {
        pcntl_waitpid($pid, $status);

        return pcntl_wexitstatus($status);
    }

    private static function getTitlePrefix() {
        return Resque::VERSION_PREFIX . '-' . (self::$prefix ? self::$prefix : 'unset');
    }
}