<?php


namespace Resque\Worker;


interface IWorkerImage {

    /**
     * @return string
     */
    function getId();

    /**
     * @return string
     */
    function getPid();

    /**
     * @return string
     */
    function getHostname();

    /**
     * @return string
     */
    function getQueue();

    /**
     * @param string $state
     *
     * @return $this
     */
    function updateState($state);

    /**
     * @return $this
     */
    function clearState();

    /**
     * @return boolean
     */
    function isAlive();

    /**
     * @return boolean
     */
    function isLocal();
}