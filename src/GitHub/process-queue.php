<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\GitHub;

$queueFile = __DIR__ . '/queue.bat';

// there can be only one
const PATH_LOCK = __FILE__ . '.tmp';
$lock = fopen(PATH_LOCK, 'a');
if (! $lock) {
    return;
}
if (! flock($lock, LOCK_EX | LOCK_NB)) {
    return;
}

$queue = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

while ($queue) {
    file_put_contents($queueFile, '', LOCK_EX);
    foreach (array_unique($queue) as $cmd) {
        echo $cmd . PHP_EOL;
        passthru($cmd);
        echo PHP_EOL . PHP_EOL;
        sleep(1);
    }
    $queue = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

flock($lock, LOCK_UN);
fclose($lock);
unlink(PATH_LOCK);