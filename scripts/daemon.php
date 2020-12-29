<?php
use Symfony\Component\Process\Process;
use Slothsoft\Unity\DaemonServer;

require_once __DIR__ . '/../vendor/autoload.php';

$daemon = new DaemonServer(
    5050,
    function(string $message) : iterable {
        echo 'Received request: ' . $message . PHP_EOL;
        $command = <<<EOT
"C:\Unity\Unity Hub\Unity Hub.exe" -- --headless help $message
EOT;
        $process = Process::fromShellCommandline($command);
        $process->start();
        foreach ($process as $type => $data) {
            if ($type === $process::OUT) {
                yield $data;
            } else {
                echo $data;
            }
        }
    }
);
$daemon->run();