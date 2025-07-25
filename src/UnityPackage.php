<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Configuration\ConfigurationField;

class UnityPackage {

    public const ENV_EMPTY_MANIFEST = 'UNITY_EMPTY_MANIFEST';

    private static function emptyManifestFile(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField();
        }
        return $field;
    }

    public static function setEmptyManifestFile(string $value): void {
        self::emptyManifestFile()->setValue($value);
    }

    public static function getEmptyManifestFile(): string {
        $file = getenv(self::ENV_EMPTY_MANIFEST);
        if ($file and is_file($file)) {
            return $file;
        }

        return self::emptyManifestFile()->getValue();
    }

    const PACKAGES_DIRECTORY = DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR;

    const MANIFEST_FILE = DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json';

    /** @var UnityPackageInfo */
    private UnityPackageInfo $info;

    /** @var UnityEditor */
    private UnityEditor $editor;

    public function __construct(UnityPackageInfo $info, UnityEditor $editor) {
        $this->info = $info;
        $this->editor = $editor;
    }

    public function __toString(): string {
        return $this->info->path;
    }

    public function createEmptyProject(string $path): UnityProject {
        $project = $this->editor->createEmptyProject($path);

        $path = $project->getProjectPath();

        FileSystem::copy(self::getEmptyManifestFile(), $path . self::MANIFEST_FILE);

        FileSystem::copy($this->info->path, $path . self::PACKAGES_DIRECTORY . $this->info->getPackageName());

        return $project;
    }

    public function getEditorVersion(): string {
        return $this->editor->version;
    }

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }

    public function ensureEditorIsLicensed(string $projectPath): bool {
        return $this->editor->isLicensed($projectPath) or $this->editor->license($projectPath);
    }
}

