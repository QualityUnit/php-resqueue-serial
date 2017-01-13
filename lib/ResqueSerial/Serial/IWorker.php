<?php


namespace ResqueSerial\Serial;


interface IWorker {

    function work();

    function shutdown();
}