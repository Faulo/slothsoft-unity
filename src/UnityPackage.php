<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;

class UnityPackage {

    const DIRECTORY_PACKAGE = DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR;

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

        FileSystem::copy($this->info->path, $path . self::DIRECTORY_PACKAGE . $this->info->getPackageName());

        return $project;
    }

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }
}

