<?php

namespace Resque\Process;

interface IStandaloneProcess {

    /**
     * @return IProcessImage
     */
    public function getImage();

    /**
     * Registers worker before work.
     * All initialization code and handler registrations should be done here.
     *
     * @return void
     * @throws \RuntimeException when worker can't be properly registered
     */
    public function register();

    /**
     * Unregister worker after work.
     * This should never fail.
     *
     * @return void
     */
    public function unregister();

    /**
     * Executes work.
     *
     * @return void
     * @throws \RuntimeException when worker wasn't registered before executing this
     */
    public function work();
}