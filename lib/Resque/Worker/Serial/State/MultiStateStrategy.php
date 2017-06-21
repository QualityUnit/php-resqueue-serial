<?php

namespace ResqueSerial\Resque\Worker\Serial\State;


use Resque\Job\IJobSource;
use Resque\Job\Reservations\IStrategy;
use Resque\Job\Reservations\TerminateException;
use Resque\Job\Reservations\TerminateStrategy;
use Resque\Job\Reservations\WaitException;
use Resque\Queue\ConfigManager;


class MultiStateStrategy extends TerminateStrategy {

    /** @var ConfigManager */
    private $queueConfig;

    public function __construct(IStrategy $strategy, ConfigManager $queueConfig,
                                $requiredWaits = 2) {
        parent::__construct($strategy, $requiredWaits);
        $this->queueConfig = $queueConfig;
    }

    function reserve(IJobSource $source) {
        try {
            return parent::reserve($source);
        } catch (TerminateException $e) {
            if ($this->canTerminate()) {
                throw $e;
            } else {
                throw new WaitException();
            }
        }
    }

    private function canTerminate() {
        return $this->queueConfig->hasChanges();
    }
}