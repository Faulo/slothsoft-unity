<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;
use DOMDocument;
use InvalidArgumentException;
use LogicException;

class UnityProject {

    /** @var UnityProjectInfo */
    private UnityProjectInfo $info;

    /** @var UnityEditor */
    private UnityEditor $editor;

    public function __construct(UnityProjectInfo $info, UnityEditor $editor) {
        $this->info = $info;
        $this->editor = $editor;
    }

    public function __toString(): string {
        return $this->info->path;
    }

    public function getProjectPath(): string {
        return $this->info->path;
    }

    public function getEditorVersion(): string {
        return $this->editor->version;
    }

    public function getScriptingBackend(): int {
        $backends = $this->getSetting('scriptingBackend', []);
        return $backends['Standalone'] ?? UnityBuildTarget::BACKEND_MONO;
    }

    public function hasSetting(string $key): bool {
        return isset($this->info->settings[$key]);
    }

    public function getSetting(string $key, $defaultValue = null) {
        return $this->info->settings[$key] ?? $defaultValue;
    }

    public function getAssetFiles(): iterable {
        $path = $this->info->path . DIRECTORY_SEPARATOR . 'Assets';
        $directory = new \RecursiveDirectoryIterator($path);
        $directoryIterator = new \RecursiveIteratorIterator($directory);
        foreach ($directoryIterator as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    public function runTests(string ...$testPlatforms): DOMDocument {
        $doc = new DOMDocument();

        $rootNode = $doc->createElement('test-run');
        $attributes = [];
        $attributes['testcasecount'] = 0;
        $attributes['total'] = 0;
        $attributes['passed'] = 0;
        $attributes['failed'] = 0;
        $attributes['inconclusive'] = 0;
        $attributes['skipped'] = 0;
        $attributes['asserts'] = 0;

        foreach ($testPlatforms as $testPlatform) {
            $resultsFile = temp_file(__CLASS__);

            $this->execute('-runTests', '-testResults', $resultsFile, '-testPlatform', $testPlatform);

            if (! is_file($resultsFile)) {
                throw new LogicException("Failed to create test results for test mode '$testPlatform' in file '$resultsFile'.");
            }

            $resultsDoc = DOMHelper::loadDocument($resultsFile);
            foreach ($resultsDoc->documentElement->attributes as $attr) {
                if (isset($attributes[$attr->name])) {
                    $attributes[$attr->name] += (int) $attr->value;
                }
            }
            foreach ($resultsDoc->documentElement->childNodes as $node) {
                $rootNode->appendChild($doc->importNode($node, true));
            }
        }

        foreach ($attributes as $key => $val) {
            $rootNode->setAttribute($key, (string) $val);
        }
        $doc->appendChild($rootNode);

        return $doc;
    }

    private const BUILD_FOLDERS = [
        '_BurstDebugInformation_DoNotShip',
        '_BackUpThisFolder_ButDontShipItWithYourGame'
    ];

    public function build(string $target, string $buildPath): Process {
        if (! is_dir($buildPath)) {
            mkdir($buildPath, 0777, true);
        }
        if (realpath($buildPath) === false) {
            throw new InvalidArgumentException("Failed to resolve build path '$buildPath'!");
        }
        $buildPath = realpath($buildPath);

        FileSystem::removeDir($buildPath, true);

        $this->editor->installModules(...UnityBuildTarget::getEditoModules($target, $this->getScriptingBackend()));

        $buildExecutable = UnityBuildTarget::getBuildExecutable($target, $this->getSetting('productName'));

        $result = $this->execute('-quit', ...UnityBuildTarget::getBuildParameters($target, $buildPath . DIRECTORY_SEPARATOR . $buildExecutable));

        foreach (self::BUILD_FOLDERS as $folder) {
            FileSystem::removeDir($buildPath . DIRECTORY_SEPARATOR . pathinfo($buildExecutable, PATHINFO_FILENAME) . $folder);
        }

        return $result;
    }

    public function executeMethod(string $method, array $args): Process {
        return $this->execute('-quit', '-executeMethod', $method, ...$args);
    }

    public function execute(string ...$arguments): Process {
        return $this->editor->execute('-projectPath', $this->info->path, ...$arguments);
    }

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }

    public function ensureEditorIsLicensed(): bool {
        return $this->editor->isLicensed($this->info->path) or $this->editor->license($this->info->path);
    }
}

