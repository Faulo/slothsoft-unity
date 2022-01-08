<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RuntimeException;
use Spyc;

class UnityProjectInfo {

    const FILE_VERSION = '/ProjectSettings/ProjectVersion.txt';

    const FILE_SETTINGS = '/ProjectSettings/ProjectSettings.asset';

    const FILE_PACKAGES = '/Packages/packages-lock.json';

    public static function find(string $directory): ?UnityProjectInfo {
        foreach (self::findAll($directory) as $info) {
            return $info;
        }
        return null;
    }

    public static function findAll(string $directory): iterable {
        if (is_dir($directory)) {
            $iterator = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($directory), function (\SplFileInfo $file, string $path, RecursiveDirectoryIterator $iterator): bool {
                return $file->isDir() and $file->getBasename() !== '..';
            });
            foreach ($iterator as $file) {
                $path = $file->getRealPath();
                if (is_file($path . self::FILE_VERSION) and is_file($path . self::FILE_SETTINGS) and is_file($path . self::FILE_PACKAGES)) {
                    yield new UnityProjectInfo($file->getRealPath());
                }
            }
        }
    }

    /** @var string */
    public $path;

    /** @var string */
    public $editorVersion;

    /** @var array */
    public $settings;

    /** @var array */
    public $packages;

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
        throw new RuntimeException("Unable to determine editor version for project '$this->path'!");
    }

    private function loadSettings(): array {
        $settings = Spyc::YAMLLoad($this->path . self::FILE_SETTINGS);
        if (is_array($settings) and isset($settings['PlayerSettings'])) {
            return $settings['PlayerSettings'];
        }
        throw new RuntimeException("Unable to determine settings for project '$this->path'!");
    }

    private function loadPackages(): array {
        $packages = json_decode(file_get_contents($this->path . self::FILE_PACKAGES), true);
        if (is_array($packages) and isset($packages['dependencies'])) {
            return $packages['dependencies'];
        }
        throw new RuntimeException("Unable to determine packages for project '$this->path'!");
    }
}

