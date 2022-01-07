<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
        assert(is_dir($directory), "Invalid directory: '$directory'");
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            yield from self::findProjectInDirectory($file);
        }
    }

    private static function findProjectInDirectory(\SplFileInfo $file): iterable {
        if (! $file->isDir()) {
            return;
        }
        $path = $file->getRealPath();
        if (is_file($path . self::FILE_VERSION) and is_file($path . self::FILE_SETTINGS) and is_file($path . self::FILE_PACKAGES)) {
            yield new UnityProjectInfo($path);
        }
    }

    public $path;

    public $editorVersion;

    public $settings;

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

