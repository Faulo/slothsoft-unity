<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;

class UnityPackageInfo {

    const FILE_PACKAGE = '/package.json';

    public static function find(string $directory, bool $includeSubdirectories = false): ?UnityPackageInfo {
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

    private static function create(string $directory): ?UnityPackageInfo {
        if (is_dir($directory) and is_file($directory . self::FILE_PACKAGE)) {
            return new UnityPackageInfo($directory);
        }
        return null;
    }

    /** @var string */
    public string $path;

    /** @var array */
    public array $package;

    private function __construct(string $path) {
        $this->path = $path;
        $this->package = $this->loadPackage();
    }

    public function getPackageName(): string {
        return $this->package['name'];
    }

    public function getMinEditorVersion(): string {
        return $this->package['unity'];
    }

    private function loadPackage(): array {
        $package = json_decode(file_get_contents($this->path . self::FILE_PACKAGE), true);
        if (is_array($package)) {
            return $package;
        }
        throw ExecutionError::Error('AssertPackageInfo', "Unable to determine packages for project '$this->path'!");
    }
}

