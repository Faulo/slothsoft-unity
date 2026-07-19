<?php
declare(strict_types = 1);

fwrite(STDOUT, "fixture stdout\n");
fwrite(STDERR, "fixture stderr\n");

exit((int) ($argv[1] ?? 0));
