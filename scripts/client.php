<?php
use Slothsoft\Unity\DaemonClient;

require_once __DIR__ . '/../vendor/autoload.php';

$daemon = new DaemonClient(5050);
foreach ($daemon->call('help') as $response) {
    echo $response;
}