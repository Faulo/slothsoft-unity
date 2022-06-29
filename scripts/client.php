<?php
use Slothsoft\Unity\DaemonClient;

array_shift($_SERVER['argv']);
$_SERVER['argc'] --;

foreach ([
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../vendor/autoload.php'
] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

$daemon = new DaemonClient(5050);
foreach ($daemon->call('help') as $response) {
    echo $response;
}