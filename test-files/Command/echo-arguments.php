<?php
declare(strict_types = 1);

// The first argument is the requested exit code. All remaining arguments are echoed.
foreach (array_slice($argv, 2) as $argument) {
    $middle = intdiv(strlen($argument), 2);
    fwrite(STDOUT, substr($argument, 0, $middle));
    fflush(STDOUT);
    usleep(20000);
    fwrite(STDOUT, substr($argument, $middle) . "\n");
    fwrite(STDERR, $argument . "\n");
}
exit((int) $argv[1]);
