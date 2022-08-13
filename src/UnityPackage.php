<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Configuration\ConfigurationField;

class UnityPackage {

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

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }
}

