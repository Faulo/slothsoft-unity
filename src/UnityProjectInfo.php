<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use Spyc;

class UnityProjectInfo {

    const FILE_VERSION = '/ProjectSettings/ProjectVersion.txt';

    const FILE_SETTINGS = '/ProjectSettings/ProjectSettings.asset';

    const FILE_PACKAGES = '/Packages/packages-lock.json';

    public static function find(string $directory, bool $includeSubdirectories = false): ?UnityProjectInfo {
        if ($includeSubdirectories) {
            foreach (self::findAll($directory) as $info) {
                return $info;
            }
            return null;
        } else {
            return self::create($directory);
        }
    }

    public static function findAll(string $directory): iterable {
        if (is_dir($directory)) {
            $iterator = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($directory), function (\SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator): bool {
                return $file->isDir() and $file->getBasename() !== '..';
            });
            foreach ($iterator as $file) {
                if ($project = self::create($file->getRealPath())) {
                    yield $project;
                }
            }
        }
    }

    private static function create(string $directory): ?UnityProjectInfo {
        if (is_dir($directory) and is_file($directory . self::FILE_VERSION) and is_file($directory . self::FILE_SETTINGS) and is_file($directory . self::FILE_PACKAGES)) {
            return new UnityProjectInfo($directory);
        }
        return null;
    }

    /** @var string */
    public string $path;

    /** @var string */
    public string $editorVersion;

    /** @var array */
    public array $settings;

    /** @var array */
    public array $packages;

    private function __construct(string $path) {
        $this->path = $path;
        $this->editorVersion = $this->loadEditorVersion();
        $this->settings = $this->loadSettings();
        $this->packages = $this->loadPackages();
    }

    private function loadEditorVersion(): string {
        $unityVersion = file_get_contents($this->path . self::FILE_VERSION);
        $match = [];
        if (preg_match('~m_EditorVersion: (.+)~', $unityVersion, $match)) {
            return trim($match[1]);
        }
        throw ExecutionError::Error('AssertEditorVersion', "Unable to determine editor version for project '$this->path'!");
    }

    private function loadSettings(): array {
        $settings = Spyc::YAMLLoad($this->path . self::FILE_SETTINGS);
        if (is_array($settings) and isset($settings['PlayerSettings'])) {
            return $settings['PlayerSettings'];
        }
        throw ExecutionError::Error('AssertProjectSettings', "Unable to determine settings for project '$this->path'!");
    }

    private function loadPackages(): array {
        $packages = json_decode(file_get_contents($this->path . self::FILE_PACKAGES), true);
        if (is_array($packages) and isset($packages['dependencies'])) {
            return $packages['dependencies'];
        }
        throw ExecutionError::Error('AssertDependencies', "Unable to determine packages for project '$this->path'!");
    }
}

