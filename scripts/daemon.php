<?php
use Symfony\Component\Process\Process;
use Slothsoft\Core\Calendar\DateTimeFormatter;
use Slothsoft\Unity\DaemonServer;

require_once __DIR__ . '/../vendor/autoload.php';

$daemon = new DaemonServer(5050, function (string $message): iterable {
    $command = <<<EOT
"C:\Unity\Unity Hub\Unity Hub.exe" -- --headless $message
EOT;
    printf('[%s] %s%s', date(DateTimeFormatter::FORMAT_DATETIME), $command, PHP_EOL);
    $process = Process::fromShellCommandline($command);
    $process->setTimeout(0);
    $process->start();
    foreach ($process as $type => $data) {
        if ($type === $process::OUT) {
            yield $data;
        } else {
            printf('[%s] %s%s', date(DateTimeFormatter::FORMAT_DATETIME), $data, PHP_EOL);
        }
    }
    yield PHP_EOL;
});
$daemon->run();