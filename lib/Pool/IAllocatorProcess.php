<?php

namespace Resque\Pool;

use Resque\Process\IStandaloneProcess;

interface IAllocatorProcess extends IStandaloneProcess {

    public function revertBuffer();

}