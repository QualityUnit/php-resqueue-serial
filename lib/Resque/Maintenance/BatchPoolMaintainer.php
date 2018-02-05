<?php

namespace Resque\Maintenance;

use Resque;
use Resque\Config\GlobalConfig;
use Resque\Key;
use Resque\Log;
use Resque\Process;
use Resque\Process\ProcessImage;
use Resque\SignalHandler;

class BatchPoolMaintainer implements ProcessMaintainer {

    /** @var string */
    private $poolName;
    /** @var string */
    private $processSetKey;

    /**
     * @param string $poolName
     */
    public function __construct($poolName) {
        $this->poolName = $poolName;
        $this->processSetKey = Key::localBatchPoolProcesses($this->poolName);
    }


    /**
     * @return ProcessImage[]
     */
    public function getLocalProcesses() {
        $workerIds = Resque::redis()->sMembers($this->processSetKey);

        $images = [];
        foreach ($workerIds as $workerId) {
            $images[] = WorkerImage::fromId($workerId);
        }

        return $images;
    }

    /**
     * Cleans up and recovers local processes.
     */
    public function maintain() {
        $pool = GlobalConfig::getInstance()->getBatchPoolConfig()->getPool($this->poolName);

        $unitsLimit = $pool->getUnitCount();
        $workersPerUnit = $pool->getWorkersPerUnit();

        $unitsAlive = $this->cleanupUnits($unitsLimit, $workersPerUnit);
        for ($i = $unitsAlive; $i < $unitsLimit; $i++) {
            $this->createUnit($workersPerUnit);
        }
    }

    /**
     * @param int $unitsLimit
     * @param int $workersPerUnit
     *
     * @return int
     */
    private function cleanupUnits($unitsLimit, $workersPerUnit) {
        $count = 0;
        foreach ($this->getLocalUnits() as $unitId) {
            if ($count < $unitsLimit) {
                $this->maintainUnitWorkers($workersPerUnit, $unitId);
                $count++;
                continue;
            }

            $this->removeWorkers($unitId);
            $this->clearQueue($unitId);
        }

        return $count;
    }

    private function cleanupWorkers($unitId, $workersPerUnit) {
        $alive = 0;
        foreach ($this->getUnitProcesses($unitId) as $image) {
            // cleanup if dead
            if (!$image->isAlive()) {
                Resque::redis()->sRem($this->processSetKey, $image->getId());
                continue;
            }

            // kill and cleanup
            if ($alive >= $workersPerUnit) {
                $this->terminateWorker($image);
                continue;
            }

            $alive++;
        }

        return $alive;
    }

    private function clearQueue($unitId) {
        while ($queue = Resque::redis()->sPop(Key::batchPoolUnitQueueSet($this->poolName, $unitId))) {
            //TODO: this parsing is not very nice, has ta be a better way
            list(, $batchId) = explode(':', $queue, 2);
            if ($batchId) {
                Resque::redis()->lPush(Key::committedBatchList(), $batchId);
            }
        }
    }

    /**
     * @param int $workersPerUnit
     */
    private function createUnit($workersPerUnit) {
        //TODO: just generate something
        $unitId = gethostname() . '_' . md5(uniqid('', true));
        Resque::redis()->zAdd(
            Key::batchPoolQueuesSortedSet($this->poolName),
            0,
            Key::batchPoolUnitQueueSet($this->poolName, $unitId)
        );

        $this->maintainUnitWorkers($workersPerUnit, $unitId);
    }

    private function forkWorker($unitId) {
        $pid = Process::fork();
        if ($pid === false) {
            Log::emergency('Unable to fork. Function pcntl_fork is not available.');

            return;
        }

        if ($pid === 0) {
            SignalHandler::instance()->unregisterAll();

            $image = WorkerImage::create($this->poolName, $unitId);
            $worker = new BatchWorkerProcess($image);
            if ($worker === null) {
                exit(0);
            }

            try {
                $worker->register();
                $worker->work();
            } catch (\Throwable $t) {
                Log::error('Worker process failed.', [
                    'exception' => $t,
                    'process_id' => $image->getId()
                ]);
            } finally {
                $worker->unregister();
            }
            exit(0);
        }
    }

    /**
     * @return string[]
     */
    private function getLocalUnits() {
        //TODO: return only local, this returns all, there must be a way how to identify them
        return Resque::redis()->zRevRange(Key::batchPoolQueuesSortedSet($this->poolName), 0, -1);
    }

    /**
     * @param string $unitId
     *
     * @return ProcessImage[]
     */
    private function getUnitProcesses($unitId) {
        $result = [];
        $poolWorkers = Resque::redis()->sMembers(Key::localBatchPoolProcesses($this->poolName));

        //TODO: this parsing is not very nice, has ta be a better way
        foreach ($poolWorkers as $worker) {
            list($workerId,) = explode('_', $worker, 2);
            if ($workerId === $unitId) {
                $result[] = $worker;
            }
        }

        return $result;
    }

    /**
     * @param $workersPerUnit
     * @param $unitId
     */
    private function maintainUnitWorkers($workersPerUnit, $unitId) {
        $alive = $this->cleanupWorkers($unitId, $workersPerUnit);

        for ($i = $alive; $i < $workersPerUnit; $i++) {
            $this->forkWorker($unitId);
        }
    }

    private function removeWorkers($unitId) {
        foreach ($this->getUnitProcesses($unitId) as $image) {
            $this->terminateWorker($image);
        }
    }

    /**
     * @param ProcessImage $image
     */
    private function terminateWorker(ProcessImage $image) {
        Log::notice("Terminating {$this->poolName} worker process");
        posix_kill($image->getPid(), SIGTERM);
    }
}