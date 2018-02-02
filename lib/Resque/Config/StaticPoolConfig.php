<?php

namespace Resque\Config;

class StaticPoolConfig {

    const WORKER_COUNT = 'worker_count';

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
     * @return StaticPool
     * @throws ConfigException
     */
    public function getPool($poolName) {
        $config = $this->pools[$poolName] ?? null;
        $workerCount = $config[self::WORKER_COUNT] ?? null;
        if (!isset($config, $workerCount)) {
            throw new ConfigException("Invalid $poolName static pool configuration.");
        }

        return new StaticPool($poolName, $workerCount);
    }

    /**
     * @return string[]
     */
    public function getPoolNames() {
        return array_keys($this->pools);
    }
}