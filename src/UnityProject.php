<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Symfony\Component\Process\Process;
use DOMDocument;

class UnityProject {

    /** @var UnityProjectInfo */
    private $info;

    /** @var UnityEditor */
    private $editor;

    public function __construct(UnityProjectInfo $info, UnityEditor $editor) {
        $this->info = $info;
        $this->editor = $editor;
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

    private function runSingleTest(string $resultsFile, string $testPlatform = 'EditMode'): int {
        $process = $this->createEditorProcess('-runTests', '-testResults', $resultsFile, '-testPlatform', $testPlatform);
        if (UnityHub::getLoggingEnabled()) {
            echo $process->getCommandLine() . PHP_EOL;
            $process->setTimeout(0);
            $process->start();
            foreach ($process as $data) {
                echo $data;
            }
        } else {
            $process->run();
            $process->wait();
        }
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
            $this->runSingleTest($resultsFile, $testPlatform);
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

    private const EDITOR_TIMEOUT = 3600;

    private function createEditorProcess(string ...$args): Process {
        assert($this->editor->isInstalled());
        $args = array_merge([
            $this->editor->executable,
            '-accept-apiupdate',
            '-batchmode',
            '-nographics',
            '-projectPath',
            $this->info->path
        ], $args);
        return new Process($args, $this->info->path, null, null, self::EDITOR_TIMEOUT);
    }

    public function ensureEditorIsInstalled(): bool {
        return $this->editor->isInstalled() or $this->editor->install();
    }
}

