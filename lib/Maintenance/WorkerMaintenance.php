<?php

namespace Resque\Maintenance;

use Resque\Job\QueuedJob;
use Resque\Log;
use Resque\Pool\IPool;
use Resque\Protocol\UniqueLock;
use Resque\Worker\WorkerImage;

class WorkerMaintenance {

    /**
     * @param IPool $pool
     * @param WorkerImage $image
     *
     * @throws \Resque\RedisError
     */
    public static function clearBuffer(IPool $pool, WorkerImage $image) {
        $source = $pool->createJobSource($image);
        while (($buffered = $source->getBuffer()->pop()) !== null) {
            Log::error("Found non-empty buffer for {$pool->getName()} worker.", [
                'raw_payload' => $buffered
            ]);

            try {
                $queuedJob = QueuedJob::decode($buffered);
            } catch (\Exception $e) {
                Log::error("Found invalid payload when cleaning non-empty buffer for {$pool->getName()} worker.", [
                    'raw_payload' => $buffered
                ]);

                continue;
            }

            $uniqueId = $queuedJob->getJob()->getUniqueId();
            if ($uniqueId !== null) {
                UniqueLock::clearLock($uniqueId);
            }
        }
    }
}