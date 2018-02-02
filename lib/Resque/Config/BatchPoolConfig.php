<?php

namespace Resque\Config;

use Resque\Pool\BatchPool;

class BatchPoolConfig {

    const UNIT_COUNT = 'unit_count';
    const WORKERS_PER_UNIT = 'workers_per_unit';

    /** @var string[][] */
    private $pools;

    /**
     * @param string[][] $poolSection
     */
    public function __construct($poolSection) {
        $this->pools = $poolSection;
    }

    /**
     * @param string $poolName
     *
     * @return BatchPool
     * @throws ConfigException
     */
    public function getPool($poolName) {
        $config = $this->pools[$poolName] ?? null;
        $unitCount = $config[self::UNIT_COUNT] ?? null;
        $workersPerUnit = $config[self::WORKERS_PER_UNIT] ?? null;
        if (!isset($config, $unitCount, $workersPerUnit)) {
            throw new ConfigException("Invalid $poolName batch pool configuration.");
        }

        return new BatchPool($poolName, $unitCount, $workersPerUnit);
    }
}