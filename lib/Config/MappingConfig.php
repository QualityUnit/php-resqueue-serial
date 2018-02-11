<?php

namespace Resque\Config;

class MappingConfig {

    const DEFAULT_KEY = 'default';
    const DEFAULT_SECTION = 'default';
    /**
     * Mapping
     * Job Name => Pool Name
     *
     * @var string[]
     */
    private $default;

    /**
     * Source Id => Mapping
     *
     * @var string[][]
     */
    private $sources;

    /**
     * @param $mappingSection
     */
    public function __construct($mappingSection) {
        $this->default = $mappingSection[self::DEFAULT_SECTION] ?? null;
        if (!\is_array($this->default) || empty($this->default[self::DEFAULT_KEY])) {
            throw new \RuntimeException('Default mapping missing.');
        }

        unset($mappingSection[self::DEFAULT_SECTION]);

        $this->sources = $mappingSection;
    }

    /**
     * @param string $sourceId
     * @param string $jobName
     *
     * @return string Resolved pool name.
     */
    public function resolvePoolName($sourceId, $jobName) {
        $poolName = null;
        $sourceMapping = $this->sources[$sourceId] ?? null;

        if ($sourceMapping !== null) {
            $poolName = $sourceMapping[$jobName] ?? null;
        }

        if ($poolName === null) {
            $poolName = $this->default[$jobName] ?? $this->default[self::DEFAULT_KEY];
        }

        return $poolName;
    }
}