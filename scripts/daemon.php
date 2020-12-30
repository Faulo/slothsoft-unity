<?php
use Symfony\Component\Process\Process;
use Slothsoft\Core\Calendar\DateTimeFormatter;
use Slothsoft\Unity\DaemonServer;

require_once __DIR__ . '/../vendor/autoload.php';

function print_message(string $message) {
    printf('[%s] %s%s', date(DateTimeFormatter::FORMAT_DATETIME), $message, PHP_EOL);
}

$daemon = new DaemonServer(5050, function (string $message): iterable {
    $args = json_decode($message, true);
    $process = new Process($args);
    print_message($process->getCommandLine());
    $process->setTimeout(0);
    $process->start();
    foreach ($process as $type => $data) {
        if ($type === $process::OUT) {
            yield $data;
        } else {
            print_message($data);
        }
    }
    yield PHP_EOL;
});
print_message('Starting Unity Daemon');
$daemon->run();