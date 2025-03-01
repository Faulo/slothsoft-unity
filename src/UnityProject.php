<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;
use DOMDocument;

class UnityProject {

    /** @var UnityProjectInfo */
    private UnityProjectInfo $info;

    /** @var UnityHub */
    private UnityHub $hub;

    /** @var UnityEditor */
    private ?UnityEditor $editor = null;

    private function initEditor(): void {
        if (! $this->editor) {
            $this->editor = $this->hub->getEditorByVersion($this->info->editorVersion);
            $this->editor->changeset = $this->info->editorChangeset;
        }
    }

    public function __construct(UnityProjectInfo $info, UnityHub $hub) {
        $this->info = $info;
        $this->hub = $hub;
    }

    public function __toString(): string {
        return $this->info->path;
    }

    public function getProjectPath(): string {
        return $this->info->path;
    }

    public function setProjectVersion(string $version): void {
        $this->info->writeSetting('bundleVersion', $version);
    }

    public function getProjectVersion(): string {
        return (string) $this->getSetting('bundleVersion', '');
    }

    public function getEditorVersion(): string {
        return $this->info->editorVersion;
    }

    public function getScriptingBackend(): int {
        $backends = $this->getSetting('scriptingBackend', []);
        return isset($backends['Standalone']) ? (int) $backends['Standalone'] : UnityBuildTarget::BACKEND_MONO;
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
        $doc = new DOMDocument('1.0', 'UTF-8');

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

            try {
                $process = $this->execute('-runTests', '-testResults', $resultsFile, '-testPlatform', $testPlatform);
                if (! is_file($resultsFile)) {
                    $message = "Failed to create results for test mode '$testPlatform'!";
                    $matches = [];
                    if (preg_match('~(An error occurred.+)~sui', $process->getOutput(), $matches)) {
                        $message .= PHP_EOL . PHP_EOL . trim($matches[1]);
                    }
                    if (preg_match('~(##### Output.+)Aborting batchmode due to failure~sui', $process->getOutput(), $matches)) {
                        $message .= PHP_EOL . PHP_EOL . trim($matches[1]);
                    }
                    throw ExecutionError::Error('AssertTestResult', $message, $process);
                }
            } catch (ExecutionError $e) {
                if (! is_file($resultsFile)) {
                    throw $e;
                }
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
            throw ExecutionError::Error('AssertDirectory', "Failed to resolve build path '$buildPath'!");
        }
        $buildPath = realpath($buildPath);

        FileSystem::removeDir($buildPath, true);

        $this->initEditor();

        $this->editor->installModules(...UnityBuildTarget::getEditoModules($target, $this->getScriptingBackend()));

        $buildExecutable = UnityBuildTarget::getBuildExecutable($target, $this->getSetting('productName'));

        $process = $this->execute('-quit', ...UnityBuildTarget::getBuildParameters($target, $buildPath . DIRECTORY_SEPARATOR . $buildExecutable));

        if ($process->getExitCode() !== 0 or ! file_exists($buildPath . DIRECTORY_SEPARATOR . $buildExecutable)) {
            $message = "Failed to compile build target '$target'!";
            $matches = [];
            if (preg_match('~(An error occurred.+)~sui', $process->getOutput(), $matches)) {
                $message .= PHP_EOL . PHP_EOL . trim($matches[1]);
            }
            if (preg_match('~(Build Finished, .+)Aborting batchmode due to failure~sui', $process->getOutput(), $matches)) {
                $message .= PHP_EOL . PHP_EOL . trim($matches[1]);
            }
            throw ExecutionError::Error('AssertBuild', $message, $process);
        }

        foreach (self::BUILD_FOLDERS as $folder) {
            FileSystem::removeDir($buildPath . DIRECTORY_SEPARATOR . pathinfo($buildExecutable, PATHINFO_FILENAME) . $folder);
        }

        return $process;
    }

    public function executeMethod(string $method, array $args): Process {
        return $this->execute('-quit', '-executeMethod', $method, ...$args);
    }

    public function startMethod(string $method, array $args): Process {
        return $this->execute('-executeMethod', $method, ...$args);
    }

    public function execute(string ...$arguments): Process {
        $this->initEditor();
        return $this->editor->execute(true, '-projectPath', $this->info->path, ...$arguments);
    }

    public function ensureEditorIsInstalled(): bool {
        $this->initEditor();
        return $this->editor->isInstalled() or $this->editor->install();
    }

    public function ensureEditorIsLicensed(): bool {
        $this->initEditor();
        return $this->editor->isLicensed($this->info->path) or $this->editor->license($this->info->path);
    }

    public function installModules(string ...$modules): bool {
        $this->initEditor();
        return $this->editor->installModules(...$modules);
    }
}

