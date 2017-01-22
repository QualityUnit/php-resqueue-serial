<?php


namespace ResqueSerial\Init;


use ResqueSerial\Serial\SerialWorkerImage;

class OrphanedSerialWorkers {


    public static function detect($queue, $logger = null) {
        foreach (SerialWorkerImage::all() as $serialWorkerId) {
            $serialImage = SerialWorkerImage::fromId($serialWorkerId);
            $parent = $serialImage->getParent();

            if (!$parent) {
            }
        }
    }

    /** @var SerialWorkerImage[][] */
    private $orphanedGroups = [];

    private function getGroup($name) {
        $group = @$this->orphanedGroups[$name];
        if (!is_array($group)) {
            return [];
        }
        return $group;
    }

    private function setGroup($name, array $groupData) {
        $this->orphanedGroups[$name] = $groupData;
    }

    private function addToGroup($name, $workerImage) {
        $group = $this->getGroup($name);
        $group[] = $workerImage;
        $this->setGroup($name, $group);
    }

    /**
     * @return SerialWorkerImage[]
     */
    public function popGroup() {
        $popped = array_pop($this->orphanedGroups);
        if ($popped === null) {
            return [];
        }
        return $popped;
    }
}