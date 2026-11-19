<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RuntimeException;

/**
 * Prepares cached and freshly installed Linux 2021.3 editors before execution.
 */
final class LinuxBeeCompatibility {
    private const MARKER = "slothsoft/unity-bee-v1\n";

    private const WRAPPER = <<<'SH'
#!/bin/sh
# slothsoft/unity-bee-v1: legacy Bee can deadlock while joining its stdin canary.
for argument in "$@"; do
    shift
    if [ "$argument" != '--stdin-canary' ]; then
        set -- "$@" "$argument"
    fi
done
exec "$0.unity-original" "$@"

SH;

    public static function prepare(string $version, string $executable): void {
        if (PHP_OS_FAMILY !== 'Linux' or ! preg_match('/^2021\.3\.\d+[fp]\d+$/D', $version)) {
            return;
        }
        $bee = dirname($executable) . '/Data/bee_backend';
        $backup = $bee . '.unity-original';
        $marker = $bee . '.unity-marker';
        $lockPath = $bee . '.unity-lock';
        if (self::isPrepared($bee, $backup, $marker)) {
            return;
        }

        $lock = is_link($lockPath) ? false : @fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException("Cannot prepare Linux Unity $version Bee at '$bee'. Prepare the editor once in a writable cache before mounting it read-only.");
        }
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot lock Bee compatibility preparation at '$bee'.");
            }
            clearstatcache();
            if (self::isPrepared($bee, $backup, $marker)) {
                return;
            }
            $original = self::read($bee);
            if (! str_starts_with($original, "\x7fELF")) {
                throw new RuntimeException("Unrecognized Bee replacement at '$bee'; restore the original editor binary before preparing compatibility.");
            }
            if (file_exists($backup) and self::read($backup) !== $original) {
                self::validateBackup($backup, $marker);
            }
            $permissions = fileperms($bee);
            if ($permissions === false or ! is_executable($bee)) {
                throw new RuntimeException("Bee at '$bee' must be executable before preparing compatibility.");
            }
            // Publish the backup and marker first. Atomic replacement leaves the
            // live executable usable throughout preparation, including Hub repairs.
            self::replace($backup, $original, $permissions & 0777);
            self::replace($marker, self::MARKER . hash('sha256', $original) . "\n", 0644);
            self::replace($bee, self::WRAPPER, $permissions & 0777);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @phpstan-impure The editor cache can change while waiting for the lock. */
    private static function isPrepared(string $bee, string $backup, string $marker): bool {
        if (is_link($bee) or is_link($backup) or is_link($marker)) {
            throw new RuntimeException("Refusing to replace a symlink at '$bee'.");
        }
        if (self::read($bee) !== self::WRAPPER) {
            return false;
        }
        self::validateBackup($backup, $marker);
        if (! is_executable($bee) or ! is_executable($backup)) {
            throw new RuntimeException("Prepared Bee and its original at '$bee' must both be executable.");
        }
        return true;
    }

    private static function validateBackup(string $backup, string $marker): void {
        $original = self::read($backup);
        if (! str_starts_with($original, "\x7fELF")
            or @file_get_contents($marker) !== self::MARKER . hash('sha256', $original) . "\n") {
            throw new RuntimeException("Unrecognized Bee backup at '$backup'; restore the original editor installation.");
        }
    }

    private static function read(string $path): string {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Cannot read Bee compatibility file '$path'. Restore the editor installation or prepare it in a writable cache.");
        }
        return $contents;
    }

    private static function replace(string $path, string $contents, int $permissions): void {
        $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        try {
            if (@file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)
                or ! @chmod($temporary, $permissions) or ! @rename($temporary, $path)) {
                throw new RuntimeException("Cannot atomically prepare '$path'; ensure the editor cache is writable.");
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
