<?php

abstract class Filler implements Iterator {
    protected $initialAmount = 100;

    protected $time;
    protected $amount;

    protected $period = 100;

    public function current() {
        return $this->amount;
    }

    public function getChange() {
        return 0;
    }

    public function getPeriod() {
        return $this->period;
    }

    public function key() {
        return $this->time;
    }

    public function next() {
        $this->time += $this->getPeriod();
        $this->amount += $this->getChange();
    }

    public function rewind() {
        $this->time = 0;
        $this->amount = $this->initialAmount;
    }

    public function setPeriod($period) {
        $this->period = $period;
    }

    public function valid() {
        return true;
    }
}

class Linear extends Filler {

    private $change;

    public function __construct($change) {
        $this->change = $change;
    }

    public function getChange() {
        return $this->change;
    }

    public function rewind() {
        parent::rewind();
    }

    public function valid() {
        if ($this->change == 0) {
            return false;
        }

        if ($this->change < 0) {
            return $this->amount >= 0;
        }

        return ($this->amount - $this->initialAmount) <= $this->change * 30;
    }
}

class Spike extends Filler {

    private $index;
    private $spike;
    private $falloff;

    public function __construct($spike, $falloff = 2) {
        $this->spike = $spike;
        $this->falloff = $falloff;
    }

    public function getChange() {
        $result = floor($this->spike / pow($this->falloff, $this->index));
        $this->index++;

        return $result;
    }

    public function rewind() {
        parent::rewind();
        $this->index = 0;
    }

    public function valid() {
        return pow($this->falloff, $this->index) < $this->spike * $this->falloff;
    }
}

class Computer {

    private $timePerJob;
    private $data;

    public function __construct($data, $timePerJob) {
        $this->timePerJob = $timePerJob;
        $this->data = $data;
    }

    public function run() {
        $done = 0;
        $lastCount = 0;
        $start = -1;
        echo str(['time', 'added', 'remain']) . PHP_EOL;
        foreach ($this->data as $time => $count) {
            if($start >= 0) {
                $done = floor(($time - $start) / $this->timePerJob);
            } else {
                $start = $time;
            }
            echo str([$time, $count, $count - $done]) . PHP_EOL;
            $lastCount = $count;
        }
        echo str([$lastCount * $this->timePerJob, $lastCount, 0]);
    }
}

function arr($iterator) {
    $array = [];
    foreach ($iterator as $time => $amount) {
        $obj = new stdClass();
        $obj->time = $time;
        $obj->amount = $amount;
        $array[] = $obj;
    }

    return $array;
}

function str($columns) {
    $result = '';
    foreach ($columns as $value) {
        $result .= str_pad($value, 8, ' ', STR_PAD_LEFT);
    }
    return $result;
}

function prt($iterator) {
    foreach ($iterator as $time => $amount) {
        echo str([$time, $amount]) . PHP_EOL;
    }
    echo PHP_EOL;
}

//prt(new Spike(5000, 200));

(new Computer(new Spike(200, 2), 3))->run();