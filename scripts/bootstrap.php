<?php
// Find and initialize Composer
$files = array(
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../autoload.php',
        __DIR__ . '/../../../../autoload.php',
        __DIR__ . '/../vendor/autoload.php',
);

$found = false;
foreach ($files as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

if (!class_exists('Composer\Autoload\ClassLoader', false)) {
    die(
            'You need to set up the project dependencies using the following commands:' . PHP_EOL .
            'curl -s http://getcomposer.org/installer | php' . PHP_EOL .
            'php composer.phar install' . PHP_EOL
    );
}

