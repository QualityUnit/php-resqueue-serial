<?php


namespace Resque\Worker;


interface IWorkerImage {

    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getPid();

    /**
     * @return string
     */
    public function getHostname();

    /**
     * @return string
     */
    public function getQueue();

    /**
     * @param string $state
     *
     * @return $this
     */
    public function updateState($state);

    /**
     * @return $this
     */
    public function clearState();

    /**
     * @return boolean
     */
    public function isAlive();

    /**
     * @return boolean
     */
    public function isLocal();
}