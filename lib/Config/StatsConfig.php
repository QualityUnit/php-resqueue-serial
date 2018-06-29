<?php

namespace Resque\Config;

use Resque\Log;

class StatsConfig {

    /** @var ConnectionInfo[] */
    private $connections = [];

    /**
     * @param mixed[] $configSection
     */
    public function __construct($configSection) {
        if (isset($configSection['host'])) {
            $connectionInfo = new ConnectionInfo($configSection);
            if ($connectionInfo->isDefault()) {
                Log::info('Default statsd connection configuration detected.');
            }
            $this->connections[] = $connectionInfo;
        } else if (\is_array($configSection)) {
            $hasDefault = false;
            foreach ($configSection as $subSection) {
                $connectionInfo = new ConnectionInfo($subSection);
                if ($connectionInfo->isDefault()) {
                    if ($hasDefault) {
                        Log::warning('More than one default statsd connection configuration detected. (misconfiguration?)');
                        continue;
                    }
                    Log::info('Default statsd connection configuration used.');
                    $hasDefault = true;
                }
                $this->connections[] = $connectionInfo;
            }
        } else {
            Log::warning('Invalid or missing statsd connection configuration.');
        }
    }

    /**
     * @return ConnectionInfo[]
     */
    public function getConnections() {
        return $this->connections;
    }
}