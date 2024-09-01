<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use Spyc;

class UnityProjectInfo {

    const FILE_VERSION = '/ProjectSettings/ProjectVersion.txt';

    const FILE_SETTINGS = '/ProjectSettings/ProjectSettings.asset';

    const FILE_MANIFEST = '/Packages/manifest.json';

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
            $directories = [];
            foreach ($iterator as $file) {
                $directories[] = $file->getRealPath();
            }
            sort($directories);
            foreach ($directories as $projectDirectory) {
                if ($project = self::create($projectDirectory)) {
                    yield $project;
                }
            }
        }
    }

    private static function create(string $directory): ?UnityProjectInfo {
        if (is_dir($directory) and is_file($directory . self::FILE_VERSION) and is_file($directory . self::FILE_SETTINGS)) {
            return new UnityProjectInfo($directory);
        }
        return null;
    }

    /** @var string */
    public string $path;

    /** @var string */
    public string $editorVersion;

    /** @var string */
    public ?string $editorChangeset;

    /** @var array */
    public array $settings;

    /** @var array */
    public array $manifest;

    /** @var array */
    public array $packages;

    private function __construct(string $path) {
        $this->path = $path;
        $this->editorVersion = $this->loadEditorVersion();
        $this->editorChangeset = $this->loadEditorChangeset();
        $this->settings = $this->loadSettings();
        $this->manifest = $this->loadManifest();
        $this->packages = $this->loadPackages();
    }

    public function writeSetting(string $key, string $value): void {
        if (! isset($this->settings[$key])) {
            return;
        }
        $yaml = file_get_contents($this->path . self::FILE_SETTINGS);
        $yaml = str_replace(" $key: {$this->settings[$key]}", " $key: $value", $yaml);
        file_put_contents($this->path . self::FILE_SETTINGS, $yaml);
        $this->settings = $this->loadSettings();
    }

    private function loadEditorVersion(): string {
        $unityVersion = file_get_contents($this->path . self::FILE_VERSION);
        $match = [];
        if (preg_match('~m_EditorVersion: (.+)~', $unityVersion, $match)) {
            return trim($match[1]);
        }
        throw ExecutionError::Error('AssertEditorVersion', "Unable to determine editor version for project '$this->path'!");
    }

    private function loadEditorChangeset(): ?string {
        $unityVersion = file_get_contents($this->path . self::FILE_VERSION);
        $match = [];
        if (preg_match('~m_EditorVersionWithRevision: .+ \((.+)\)~', $unityVersion, $match)) {
            return trim($match[1]);
        }
        return null;
    }

    private function loadSettings(): array {
        $settings = Spyc::YAMLLoad($this->path . self::FILE_SETTINGS);
        if (is_array($settings) and isset($settings['PlayerSettings'])) {
            return $settings['PlayerSettings'];
        }
        throw ExecutionError::Error('AssertProjectSettings', "Unable to determine settings for project '$this->path'!");
    }

    private function loadPackages(): array {
        if (! file_exists($this->path . self::FILE_PACKAGES)) {
            return [];
        }
        $packages = JsonUtils::load($this->path . self::FILE_PACKAGES);
        if (is_array($packages) and isset($packages['dependencies'])) {
            return $packages['dependencies'];
        }
        throw ExecutionError::Error('AssertDependencies', "Unable to determine packages for project '$this->path'!");
    }

    private function loadManifest(): array {
        if (! file_exists($this->path . self::FILE_MANIFEST)) {
            return [];
        }

        return JsonUtils::load($this->path . self::FILE_MANIFEST);
    }

    public function saveManifest(): void {
        JsonUtils::save($this->path . self::FILE_MANIFEST, $this->manifest, 2, "\n");
    }
}

