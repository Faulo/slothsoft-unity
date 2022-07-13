<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use DOMDocument;
use Generator;
use Slothsoft\Core\FileSystem;

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

    public function executeMethod(string ...$args): int {
        array_unshift($args, '-executeMethod');
        array_unshift($args, '-quit');

        $process = $this->createEditorProcess(...$args);
        $process->run();
        $process->wait();
        return $process->getExitCode();
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

            $this->execute([
                '-runTests',
                '-testResults',
                $resultsFile,
                '-testPlatform',
                $testPlatform
            ]);

            if (is_file($resultsFile)) {
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
        }

        foreach ($attributes as $key => $val) {
            $rootNode->setAttribute($key, (string) $val);
        }
        $doc->appendChild($rootNode);

        return $doc;
    }

    const BUILD_FOLDERS = [
        '_BurstDebugInformation_DoNotShip',
        '_BackUpThisFolder_ButDontShipItWithYourGame'
    ];

    public function build(string $buildPath): DOMDocument {
        $this->editor->installModules('windows', 'windows-mono', 'windows-il2cpp');

        $buildName = FileSystem::filenameSanitize($this->getSetting('productName'));
        $buildFile = $buildPath . DIRECTORY_SEPARATOR . $buildName . '.exe';

        $result = $this->execute([
            '-quit',
            '-buildWindows64Player',
            $buildFile
        ]);

        foreach (self::BUILD_FOLDERS as $folder) {
            FileSystem::removeDir($buildPath . DIRECTORY_SEPARATOR . $buildName . $folder);
        }

        $doc = new DOMDocument();
        $node = $doc->createElement('result');
        $node->textContent = $result;
        $doc->appendChild($node);

        return $doc;
    }

    private const EDITOR_TIMEOUT = 3600;

    public function execute(array $arguments): string {
        return $this->editor->execute($this->createProcessArguments($arguments));
    }

    public function executeStream(array $arguments): Generator {
        return $this->editor->executeStream($this->createProcessArguments($arguments));
    }

    private function createProcessArguments(array $arguments): array {
        return array_merge([
            '-projectPath',
            $this->info->path
        ], $arguments);
    }

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }

    public function ensureEditorIsLicensed(): bool {
        return $this->editor->isLicensed() or $this->editor->license();
    }
}

