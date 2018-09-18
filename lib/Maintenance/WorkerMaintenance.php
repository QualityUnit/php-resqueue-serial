<?php

namespace Resque\Maintenance;

use Resque\Job\JobParseException;
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
        try {
            while (($buffered = $source->getBuffer()->popJob()) !== null) {
                Log::error("Found non-empty buffer for {$pool->getName()} worker.", [
                    'payload' => $buffered->getJob()->toArray()
                ]);
                $uniqueId = $buffered->getJob()->getUniqueId();
                if ($uniqueId !== null) {
                    UniqueLock::clearLock($uniqueId);
                }
            }
        } catch (JobParseException $e) {
            Log::error("Found invalid payload when cleaning non-empty buffer for {$pool->getName()} worker.", [
                'raw_payload' => json_encode($e->getPayload(), JSON_PRETTY_PRINT)
            ]);
        }
    }
}