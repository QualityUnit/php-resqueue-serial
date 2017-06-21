<?php


namespace Resque\Job\Reservations;


use Resque\Job\IJobSource;

class TerminateStrategy implements IStrategy {

    /** @var IStrategy */
    private $strategy;
    /* @var int */
    private $requiredWaits;
    /* @var int */
    private $waitsInARow = 0;

    /**
     * @param IStrategy $strategy
     * @param int $requiredWaits
     */
    public function __construct(IStrategy $strategy, $requiredWaits = 2) {
        $this->strategy = $strategy;
        $this->requiredWaits = $requiredWaits;
    }

    /**
     * @inheritdoc
     */
    function reserve(IJobSource $source) {
        try {
            $job = $this->strategy->reserve($source);
            $this->waitsInARow = 0;

            return $job;
        } catch (WaitException $e) {
            $this->waitsInARow++;
            if ($this->waitsInARow >= $this->requiredWaits) {
                throw new TerminateException();
            }
            throw $e;
        }
    }
}