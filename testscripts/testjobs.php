<?php


class __PassJob  {

    public function perform() {
    }
}

class __EchoJob  {

    public function perform() {
        echo $this->args['message'] ?? "No 'message' to echo.";
    }
}

class __SleepJob {

    public function perform() {
        sleep((int)($this->args['sleep'] ?? 3));

        \Resque\Log::notice($this->args['message'] ?? "No 'message' to echo.");
    }
}

class __ErrorJob {

    /**
     * @throws Exception
     */
    public function perform() {
        throw new \Exception($this->args['message'] ?? "No 'message' argument set.");
    }
}

class __ExitJob  {

    public function perform() {
        exit((int)($this->args['code'] ?? 0));
    }
}