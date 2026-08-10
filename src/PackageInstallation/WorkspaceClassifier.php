<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * Classifies only the supplied path as a Unity workspace.
 *
 * A project is recognized from the same two exact-root marker files required by
 * UnityProjectInfo. Parent and child directories are deliberately never scanned.
 */
final class WorkspaceClassifier {
    private const PROJECT_VERSION_FILE = 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt';
    private const PROJECT_SETTINGS_FILE = 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset';

    public function classify(string $path): WorkspaceClassification {
        if ($path === '' or str_contains($path, "\0")) {
            throw new PackageInstallationException('Workspace path must not be empty or contain a null byte.');
        }

        if ($this->isLinkLike($path)) {
            return new WorkspaceClassification($path, WorkspaceState::INVALID);
        }

        if (! file_exists($path)) {
            return new WorkspaceClassification($path, WorkspaceState::MISSING);
        }

        if (! is_dir($path)) {
            return new WorkspaceClassification($path, WorkspaceState::INVALID);
        }

        if ($this->hasProjectMarkers($path)) {
            return new WorkspaceClassification($path, WorkspaceState::VALID_PROJECT);
        }

        return new WorkspaceClassification($path, $this->isEmptyDirectory($path) ? WorkspaceState::EMPTY : WorkspaceState::INVALID);
    }

    private function hasProjectMarkers(string $path): bool {
        return is_file($path . DIRECTORY_SEPARATOR . self::PROJECT_VERSION_FILE) and is_file($path . DIRECTORY_SEPARATOR . self::PROJECT_SETTINGS_FILE);
    }

    private function isEmptyDirectory(string $path): bool {
        $directory = @opendir($path);
        if ($directory === false) {
            throw new PackageInstallationException("Unable to inspect workspace '$path'.");
        }

        try {
            while (($entry = readdir($directory)) !== false) {
                if ($entry !== '.' and $entry !== '..') {
                    return false;
                }
            }
        } finally {
            closedir($directory);
        }

        return true;
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
}
