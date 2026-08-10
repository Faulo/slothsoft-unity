<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

use Throwable;

/**
 * Stages and transactionally activates exactly one embedded package.
 *
 * The old destination is retained as a sibling backup until the caller has
 * committed its manifest. Source links are rejected, and removal handles links
 * as nodes without following them, including Windows junctions.
 */
final class EmbeddedPackageReplacer {
    private WorkspaceClassifier $workspaceClassifier;
    private PackageMetadataReaderInterface $metadataReader;
    private ?string $packagesRoot = null;
    private ?string $destination = null;
    private ?string $staging = null;
    private ?string $backup = null;
    private bool $destinationExisted = false;
    private bool $destinationMoved = false;
    private bool $activated = false;

    public function __construct(?WorkspaceClassifier $workspaceClassifier = null, ?PackageMetadataReaderInterface $metadataReader = null) {
        $this->workspaceClassifier = $workspaceClassifier ?? new WorkspaceClassifier();
        $this->metadataReader = $metadataReader ?? new PackageMetadataReader();
    }

    /**
     * Stages a validated package without changing its final destination.
     *
     * @return string Canonical destination path inside WORKSPACE/Packages.
     */
    public function prepare(string $workspacePath, string $sourcePackagePath): string {
        if ($this->isPrepared()) {
            throw new PackageInstallationException('A package replacement is already prepared.');
        }

        $classification = $this->workspaceClassifier->classify($workspacePath);
        if ($classification->getState() !== WorkspaceState::VALID_PROJECT) {
            throw new PackageInstallationException("Workspace '$workspacePath' is not an exact Unity project root.");
        }

        $workspaceRoot = realpath($workspacePath);
        if ($workspaceRoot === false) {
            throw new PackageInstallationException("Unable to resolve workspace '$workspacePath'.");
        }

        $packagesPath = $workspaceRoot . DIRECTORY_SEPARATOR . 'Packages';
        if ($this->isLinkLike($packagesPath) or ! is_dir($packagesPath)) {
            throw new PackageInstallationException("Workspace Packages directory '$packagesPath' must be a real directory.");
        }

        $packagesRoot = realpath($packagesPath);
        if ($packagesRoot === false or ! $this->isWithin($packagesRoot, $workspaceRoot) or $this->pathsEqual($packagesRoot, $workspaceRoot)) {
            throw new PackageInstallationException("Workspace Packages directory '$packagesPath' escapes the project root.");
        }

        if ($this->isLinkLike($sourcePackagePath)) {
            throw new PackageInstallationException("Package source '$sourcePackagePath' must not be a symbolic link or junction.");
        }

        $sourceRoot = realpath($sourcePackagePath);
        if ($sourceRoot === false or ! is_dir($sourceRoot)) {
            throw new PackageInstallationException("Package source '$sourcePackagePath' is not a directory.");
        }

        $metadata = $this->metadataReader->read($sourceRoot);
        $destination = $packagesRoot . DIRECTORY_SEPARATOR . $metadata->getName();
        $this->assertDirectChild($destination, $packagesRoot);

        if ($this->pathsEqual($sourceRoot, $destination) or $this->isWithin($packagesRoot, $sourceRoot)) {
            throw new PackageInstallationException("Package source '$sourceRoot' overlaps its installation workspace.");
        }

        $destinationExists = $this->pathExists($destination);
        $this->validateDestination($destination, $packagesRoot, $destinationExists);
        if ($destinationExists and is_dir($destination) and $this->isWithin($sourceRoot, (string) realpath($destination))) {
            throw new PackageInstallationException("Package source '$sourceRoot' is inside the destination being replaced.");
        }

        $staging = $this->createStagingDirectory($packagesRoot, $metadata->getName());
        try {
            $this->copyDirectory($sourceRoot, $staging, $sourceRoot);
            $stagedMetadata = $this->metadataReader->read($staging);
            if ($stagedMetadata->getName() !== $metadata->getName()) {
                throw new PackageInstallationException("Staged package name '{$stagedMetadata->getName()}' does not match '{$metadata->getName()}'.");
            }

            $sourcePermissions = @fileperms($sourceRoot);
            if ($sourcePermissions !== false) {
                @chmod($staging, $sourcePermissions & 0777);
            }
        } catch (Throwable $exception) {
            $this->tryRemovePath($staging, $packagesRoot);
            if ($exception instanceof PackageInstallationException) {
                throw $exception;
            }
            throw new PackageInstallationException("Unable to stage embedded package '$destination': {$exception->getMessage()}", 0, $exception);
        }

        $this->packagesRoot = $packagesRoot;
        $this->destination = $destination;
        $this->staging = $staging;
        $this->destinationExisted = $destinationExists;

        return $destination;
    }

    /**
     * Moves the prepared package into place while retaining the old destination.
     */
    public function activate(): string {
        $this->assertPrepared();
        $packagesRoot = (string) $this->packagesRoot;
        $destination = (string) $this->destination;
        $staging = (string) $this->staging;

        $this->validateDestination($destination, $packagesRoot, $this->destinationExisted);

        if ($this->destinationExisted) {
            $this->backup = $this->createUnusedPath($packagesRoot, basename($destination), 'backup');
            if (! @rename($destination, $this->backup)) {
                throw new PackageInstallationException("Unable to stage existing embedded package '$destination' for replacement.");
            }
            $this->destinationMoved = true;
        }

        if (! @rename($staging, $destination)) {
            $this->restoreDestination();
            throw new PackageInstallationException("Unable to activate staged embedded package '$destination'.");
        }

        $this->staging = null;
        $this->activated = true;

        return $destination;
    }

    /**
     * Restores the old destination and removes all staging created by this run.
     */
    public function rollback(): void {
        if (! $this->isPrepared()) {
            return;
        }

        $packagesRoot = (string) $this->packagesRoot;
        $destination = (string) $this->destination;

        if ($this->activated and $this->pathExists($destination)) {
            $this->removePath($destination, $packagesRoot);
            $this->activated = false;
        }

        $this->restoreDestination();

        if ($this->staging !== null and $this->pathExists($this->staging)) {
            $this->removePath($this->staging, $packagesRoot);
            $this->staging = null;
        }

        $this->resetState();
    }

    /**
     * Permanently removes the retained destination after the manifest commits.
     */
    public function finalize(): void {
        $this->assertPrepared();
        if (! $this->activated) {
            throw new PackageInstallationException('Cannot finalize a package replacement before activation.');
        }

        if ($this->backup !== null and $this->pathExists($this->backup)) {
            $this->removePath($this->backup, (string) $this->packagesRoot);
        }

        $this->resetState();
    }

    public function abort(): void {
        $this->rollback();
    }

    /**
     * Convenience API for callers that do not need to coordinate another file.
     */
    public function replace(string $workspacePath, string $sourcePackagePath): string {
        $destination = $this->prepare($workspacePath, $sourcePackagePath);
        try {
            $this->activate();
        } catch (Throwable $exception) {
            try {
                $this->rollback();
            } catch (Throwable $rollbackException) {
                throw new PackageInstallationException("Unable to activate embedded package '$destination' and rollback failed: {$rollbackException->getMessage()}", 0, $exception);
            }
            throw $exception;
        }

        $this->finalize();

        return $destination;
    }

    private function validateDestination(string $destination, string $packagesRoot, bool $mustExist): void {
        $exists = $this->pathExists($destination);
        if ($exists !== $mustExist) {
            throw new PackageInstallationException("Embedded package destination '$destination' changed during replacement.");
        }
        if (! $exists) {
            return;
        }
        if ($this->isLinkLike($destination)) {
            throw new PackageInstallationException("Embedded package destination '$destination' must not be a symbolic link or junction.");
        }
        if (! is_dir($destination) and ! is_file($destination)) {
            throw new PackageInstallationException("Embedded package destination '$destination' has an unsupported filesystem type.");
        }

        $resolvedDestination = realpath($destination);
        if ($resolvedDestination === false or ! $this->isWithin($resolvedDestination, $packagesRoot) or ! $this->pathsEqual($resolvedDestination, $destination)) {
            throw new PackageInstallationException("Embedded package destination '$destination' escapes the Packages directory.");
        }
    }

    private function restoreDestination(): void {
        if (! $this->destinationMoved or $this->backup === null) {
            return;
        }

        $destination = (string) $this->destination;
        if ($this->pathExists($destination)) {
            throw new PackageInstallationException("Unable to restore embedded package '$destination' because the destination still exists.");
        }
        if (! @rename($this->backup, $destination)) {
            throw new PackageInstallationException("Unable to restore previous embedded package '$destination'.");
        }

        $this->backup = null;
        $this->destinationMoved = false;
    }

    private function createStagingDirectory(string $packagesRoot, string $packageName): string {
        for ($attempt = 0; $attempt < 10; $attempt ++) {
            $path = $this->createUnusedPath($packagesRoot, $packageName, 'install');
            if (@mkdir($path, 0700)) {
                return $path;
            }
        }

        throw new PackageInstallationException("Unable to create package staging directory in '$packagesRoot'.");
    }

    private function createUnusedPath(string $packagesRoot, string $packageName, string $purpose): string {
        $path = $packagesRoot . DIRECTORY_SEPARATOR . '.' . $packageName . '.' . $purpose . '-' . $this->createRandomToken();
        $this->assertDirectChild($path, $packagesRoot);
        if ($this->pathExists($path)) {
            return $this->createUnusedPath($packagesRoot, $packageName, $purpose);
        }

        return $path;
    }

    private function copyDirectory(string $source, string $target, string $sourceRoot): void {
        $directory = @opendir($source);
        if ($directory === false) {
            throw new PackageInstallationException("Unable to inspect package source directory '$source'.");
        }

        try {
            while (($entry = readdir($directory)) !== false) {
                if ($entry === '.' or $entry === '..') {
                    continue;
                }

                $sourcePath = $source . DIRECTORY_SEPARATOR . $entry;
                $targetPath = $target . DIRECTORY_SEPARATOR . $entry;
                if ($this->isLinkLike($sourcePath)) {
                    throw new PackageInstallationException("Package source contains unsupported symbolic link or junction '$sourcePath'.");
                }

                $resolvedSourcePath = realpath($sourcePath);
                if ($resolvedSourcePath === false or ! $this->isWithin($resolvedSourcePath, $sourceRoot)) {
                    throw new PackageInstallationException("Package source entry '$sourcePath' escapes '$sourceRoot'.");
                }

                if (is_dir($sourcePath)) {
                    if (! @mkdir($targetPath, 0700)) {
                        throw new PackageInstallationException("Unable to create staged package directory '$targetPath'.");
                    }
                    $this->copyDirectory($sourcePath, $targetPath, $sourceRoot);
                    $permissions = @fileperms($sourcePath);
                    if ($permissions !== false) {
                        @chmod($targetPath, $permissions & 0777);
                    }
                } elseif (is_file($sourcePath)) {
                    if (! @copy($sourcePath, $targetPath)) {
                        throw new PackageInstallationException("Unable to copy package file '$sourcePath'.");
                    }
                    $permissions = @fileperms($sourcePath);
                    if ($permissions !== false) {
                        @chmod($targetPath, $permissions & 0777);
                    }
                } else {
                    throw new PackageInstallationException("Package source contains unsupported entry '$sourcePath'.");
                }
            }
        } finally {
            closedir($directory);
        }
    }

    private function removePath(string $path, string $packagesRoot): void {
        if (! $this->isWithin($path, $packagesRoot) or $this->pathsEqual($path, $packagesRoot)) {
            throw new PackageInstallationException("Refusing to remove package entry outside '$packagesRoot'.");
        }

        if ($this->isLinkLike($path)) {
            $this->removeLinkNode($path);
            return;
        }

        $resolvedPath = realpath($path);
        if ($resolvedPath === false or ! $this->isWithin($resolvedPath, $packagesRoot) or $this->pathsEqual($resolvedPath, $packagesRoot)) {
            throw new PackageInstallationException("Refusing to remove package entry outside '$packagesRoot'.");
        }

        if (! is_dir($path)) {
            @chmod($path, 0666);
            if (! @unlink($path)) {
                throw new PackageInstallationException("Unable to remove old package entry '$path'.");
            }
            return;
        }

        @chmod($path, 0777);
        $directory = @opendir($path);
        if ($directory === false) {
            throw new PackageInstallationException("Unable to inspect old package directory '$path'.");
        }
        try {
            while (($entry = readdir($directory)) !== false) {
                if ($entry !== '.' and $entry !== '..') {
                    $this->removePath($path . DIRECTORY_SEPARATOR . $entry, $packagesRoot);
                }
            }
        } finally {
            closedir($directory);
        }

        if (! @rmdir($path)) {
            throw new PackageInstallationException("Unable to remove old package directory '$path'.");
        }
    }

    private function removeLinkNode(string $path): void {
        $removed = PHP_OS_FAMILY === 'Windows' ? (@rmdir($path) || @unlink($path)) : @unlink($path);
        if (! $removed) {
            throw new PackageInstallationException("Unable to remove package link '$path'.");
        }
    }

    private function tryRemovePath(string $path, string $packagesRoot): void {
        try {
            if ($this->pathExists($path)) {
                $this->removePath($path, $packagesRoot);
            }
        } catch (Throwable) {
            // Preserve the failure that required staging cleanup.
        }
    }

    private function assertPrepared(): void {
        if (! $this->isPrepared()) {
            throw new PackageInstallationException('No package replacement has been prepared.');
        }
    }

    private function isPrepared(): bool {
        return $this->packagesRoot !== null and $this->destination !== null;
    }

    private function resetState(): void {
        $this->packagesRoot = null;
        $this->destination = null;
        $this->staging = null;
        $this->backup = null;
        $this->destinationExisted = false;
        $this->destinationMoved = false;
        $this->activated = false;
    }

    private function assertDirectChild(string $path, string $parent): void {
        if (! $this->pathsEqual(dirname($path), $parent) or ! $this->isWithin($path, $parent)) {
            throw new PackageInstallationException("Path '$path' is not a direct child of '$parent'.");
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

        $resolvedParent = realpath(dirname($path));
        $resolvedPath = realpath($path);
        if ($resolvedParent !== false and $resolvedPath !== false) {
            $directPath = $resolvedParent . DIRECTORY_SEPARATOR . basename($path);
            if (! $this->pathsEqual($resolvedPath, $directPath)) {
                return true;
            }
        }

        return $targetStat === false or $linkStat != $targetStat;
    }

    private function isWithin(string $path, string $parent): bool {
        $path = $this->normalizePath($path);
        $parent = $this->normalizePath($parent);

        return $path === $parent or str_starts_with($path, $parent . DIRECTORY_SEPARATOR);
    }

    private function pathsEqual(string $left, string $right): bool {
        return $this->normalizePath($left) === $this->normalizePath($right);
    }

    private function normalizePath(string $path): string {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if (PHP_OS_FAMILY === 'Windows') {
            $path = strtolower($path);
        }

        return $path;
    }

    private function createRandomToken(): string {
        try {
            return bin2hex(random_bytes(12));
        } catch (Throwable $exception) {
            throw new PackageInstallationException('Unable to create a secure package staging name.', 0, $exception);
        }
    }
}
