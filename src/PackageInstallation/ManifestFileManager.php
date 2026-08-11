<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

use JsonException;
use stdClass;
use Throwable;

/**
 * Reads, validates, merges, and atomically replaces Unity manifest JSON files.
 */
final class ManifestFileManager {
    private ManifestMerger $merger;

    public function __construct(?ManifestMerger $merger = null) {
        $this->merger = $merger ?? new ManifestMerger();
    }

    public function read(string $path): stdClass {
        if ($this->isLinkLike($path) or ! is_file($path)) {
            throw new PackageInstallationException("Manifest '$path' must be a regular file.");
        }

        $json = @file_get_contents($path);
        if ($json === false) {
            throw new PackageInstallationException("Unable to read manifest '$path'.");
        }

        try {
            $manifest = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PackageInstallationException("Unable to parse manifest '$path': {$exception->getMessage()}", 0, $exception);
        }

        if (! $manifest instanceof stdClass) {
            throw new PackageInstallationException("Manifest '$path' must contain a JSON object.");
        }

        return $manifest;
    }

    public function mergeFiles(string $existingPath, string $incomingPath): stdClass {
        $existing = $this->read($existingPath);
        $incoming = $this->read($incomingPath);

        return $this->merger->merge($existing, $incoming);
    }

    public function mergeAndWrite(string $existingPath, string $incomingPath): stdClass {
        $manifest = $this->mergeFiles($existingPath, $incomingPath);
        $this->write($existingPath, $manifest);

        return $manifest;
    }

    public function write(string $path, stdClass $manifest): void {
        $stagedWrite = $this->stage($path, $manifest);
        try {
            $this->commit($stagedWrite);
        } catch (Throwable $exception) {
            $this->discard($stagedWrite);
            throw $exception;
        }
    }

    /**
     * Fully encodes and synchronizes a manifest without changing its target.
     *
     * @return array{target: string, temporary: string}
     */
    public function stage(string $path, stdClass $manifest): array {
        try {
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new PackageInstallationException("Unable to encode manifest '$path': {$exception->getMessage()}", 0, $exception);
        }

        $manifestDirectory = dirname($path);
        if ($this->isLinkLike($manifestDirectory)) {
            throw new PackageInstallationException("Manifest directory '$manifestDirectory' must be a real directory.");
        }

        $directory = realpath($manifestDirectory);
        if ($directory === false or ! is_dir($directory)) {
            throw new PackageInstallationException("Manifest directory '$manifestDirectory' does not exist.");
        }

        $fileName = basename($path);
        if ($fileName === '' or $fileName === '.' or $fileName === '..') {
            throw new PackageInstallationException("Manifest path '$path' is invalid.");
        }

        $targetPath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $this->assertWritableTarget($targetPath);

        $temporaryPath = null;
        $handle = null;
        try {
            [$temporaryPath, $handle] = $this->createTemporaryFile($directory);
            $this->writeAll($handle, $json, $targetPath);

            if (! @fflush($handle)) {
                throw new PackageInstallationException("Unable to flush temporary manifest for '$targetPath'.");
            }
            if (function_exists('fsync') and ! @fsync($handle)) {
                throw new PackageInstallationException("Unable to synchronize temporary manifest for '$targetPath'.");
            }

            if (is_file($targetPath)) {
                $permissions = @fileperms($targetPath);
                if ($permissions !== false) {
                    @chmod($temporaryPath, $permissions & 0777);
                }
            }

            if (! @fclose($handle)) {
                throw new PackageInstallationException("Unable to close temporary manifest for '$targetPath'.");
            }
            $handle = null;

            return [
                'target' => $targetPath,
                'temporary' => $temporaryPath
            ];
        } catch (Throwable $exception) {
            if (is_resource($handle)) {
                @fclose($handle);
            }
            if ($temporaryPath !== null and $this->pathExists($temporaryPath)) {
                @unlink($temporaryPath);
            }

            if ($exception instanceof PackageInstallationException) {
                throw $exception;
            }
            throw new PackageInstallationException("Unable to stage manifest '$targetPath': {$exception->getMessage()}", 0, $exception);
        }
    }

    /**
     * @param array{target: string, temporary: string} $stagedWrite
     */
    public function commit(array $stagedWrite): void {
        $targetPath = $stagedWrite['target'];
        $temporaryPath = $stagedWrite['temporary'];
        $this->assertWritableTarget($targetPath);

        if (! is_file($temporaryPath) or $this->isLinkLike($temporaryPath)) {
            throw new PackageInstallationException("Staged manifest '$temporaryPath' is not a regular file.");
        }

        if (! @rename($temporaryPath, $targetPath)) {
            throw new PackageInstallationException("Unable to atomically replace manifest '$targetPath'.");
        }
    }

    /**
     * @param array{target: string, temporary: string} $stagedWrite
     */
    public function discard(array $stagedWrite): void {
        if ($this->pathExists($stagedWrite['temporary'])) {
            @unlink($stagedWrite['temporary']);
        }
    }

    private function assertWritableTarget(string $targetPath): void {
        if ($this->isLinkLike($targetPath) or ($this->pathExists($targetPath) and ! is_file($targetPath))) {
            throw new PackageInstallationException("Manifest '$targetPath' must be a regular file.");
        }
    }

    /**
     * @return array{0: string, 1: resource}
     */
    private function createTemporaryFile(string $directory): array {
        for ($attempt = 0; $attempt < 10; $attempt ++) {
            $path = $directory . DIRECTORY_SEPARATOR . '.manifest-' . $this->createRandomToken() . '.tmp';
            $handle = @fopen($path, 'x+b');
            if (is_resource($handle)) {
                return [$path, $handle];
            }
        }

        throw new PackageInstallationException("Unable to create a temporary manifest in '$directory'.");
    }

    /**
     * @param resource $handle
     */
    private function writeAll($handle, string $contents, string $targetPath): void {
        $offset = 0;
        $length = strlen($contents);
        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));
            if ($written === false or $written === 0) {
                throw new PackageInstallationException("Unable to write temporary manifest for '$targetPath'.");
            }
            $offset += $written;
        }
    }

    private function pathExists(string $path): bool {
        return file_exists($path) or is_link($path) or @lstat($path) !== false;
    }

    private function isLinkLike(string $path): bool {
        clearstatcache(true, $path);
        if (is_link($path)) {
            return true;
        }

        $linkStat = @lstat($path);
        if ($linkStat === false) {
            return false;
        }
        $targetStat = @stat($path);

        return $targetStat === false or $linkStat != $targetStat;
    }

    private function createRandomToken(): string {
        try {
            return bin2hex(random_bytes(12));
        } catch (Throwable $exception) {
            throw new PackageInstallationException('Unable to create a secure manifest staging name.', 0, $exception);
        }
    }
}
