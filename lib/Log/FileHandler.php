<?php

namespace Resque\Log;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class FileHandler extends AbstractProcessingHandler {

    protected $url;
    private $errorMessage;
    private $dirCreated;

    /**
     * @param string $url
     * @param string|int $level The minimum logging level at which this handler will be triggered
     * @param Boolean $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(string $url, $level = Logger::DEBUG, bool $bubble = true) {
        parent::__construct($level, $bubble);
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record) {
        $this->createDir();
        file_put_contents($this->url, (string)$record['formatted'], FILE_APPEND);
    }

    private function createDir() {
        // Do not try to create dir if it has already been tried.
        if ($this->dirCreated) {
            return;
        }

        $dir = \dirname($this->url);
        if (null !== $dir && !is_dir($dir)) {
            $this->errorMessage = null;
            set_error_handler([$this, 'customErrorHandler']);
            $status = mkdir($dir, 0777, true);
            restore_error_handler();
            if (false === $status) {
                throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and its not buildable: '
                    . $this->errorMessage, $dir));
            }
        }
        $this->dirCreated = true;
    }

    private function customErrorHandler($code, $msg) {
        $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);
    }
}