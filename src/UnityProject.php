<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;
use DOMDocument;
use DOMElement;

/**
 * Represents a Unity project and exposes editor automation operations for it.
 *
 * @author Daniel Schulz
 * @since 2020-12-25
 */
final class UnityProject {
    
    private UnityProjectInfo $info;
    
    private UnityHub $hub;
    
    private ?UnityEditor $editor = null;
    
    private function initEditor(): void {
        $this->editor ??= $this->hub->getEditorByVersion($this->info->editorVersion);
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
    
    public function getPackages(): array {
        return $this->info->packages;
    }
    
    public function getScriptingBackend(): int {
        $backends = $this->getSetting('scriptingBackend', []);
        return isset($backends['Standalone']) ? (int) $backends['Standalone'] : UnityBuildTarget::BACKEND_MONO;
    }
    
    public function hasSetting(string $key): bool {
        return isset($this->info->settings[$key]);
    }
    
    public function getSetting(string $key, mixed $defaultValue = null): mixed {
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
            $process = null;
            $executionError = null;
            
            try {
                $process = $this->execute('-runTests', '-testResults', $resultsFile, '-testPlatform', $testPlatform);
            } catch (ExecutionError $e) {
                $executionError = $e;
            }
            
            $reportError = '';
            $resultsDoc = $this->loadTestReport($resultsFile, $reportError);
            if ($resultsDoc === null) {
                $this->appendTestInfrastructureError($doc, $rootNode, $testPlatform, $reportError, $executionError, $process);
                $attributes['testcasecount'] ++;
                $attributes['total'] ++;
                $attributes['failed'] ++;
                continue;
            }

            if ($executionError !== null and UnityHub::getPropagateProcessExitCodes()) {
                $warning = sprintf(
                    "Unity test mode '%s' produced a valid report despite process exit code %d. The report was accepted as authoritative.",
                    $testPlatform,
                    $executionError->getExitCode()
                );
                $warningNode = $doc->createElement('warning');
                $warningNode->textContent = $warning;
                $rootNode->appendChild($warningNode);
            }
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

    private function loadTestReport(string $resultsFile, string &$error): ?DOMDocument {
        if (! is_file($resultsFile)) {
            $error = 'Unity did not create a test report.';
            return null;
        }

        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument();
            if (! $document->load($resultsFile, LIBXML_NONET | LIBXML_PARSEHUGE)) {
                $messages = [];
                foreach (libxml_get_errors() as $xmlError) {
                    $messages[] = sprintf('line %d, column %d: %s', $xmlError->line, $xmlError->column, trim($xmlError->message));
                }
                $error = 'Unity created a malformed test report.';
                if ($messages !== []) {
                    $error .= PHP_EOL . implode(PHP_EOL, $messages);
                }
                return null;
            }
            if ($document->documentElement?->nodeName !== 'test-run') {
                $error = sprintf("Unity created an unusable test report with root element '%s'.", $document->documentElement?->nodeName ?? 'none');
                return null;
            }
            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    private function appendTestInfrastructureError(
        DOMDocument $document,
        DOMElement $root,
        string $testPlatform,
        string $reportError,
        ?ExecutionError $executionError,
        ?Process $process
    ): void {
        $suiteName = sprintf('Unity Test Runner process (%s)', $testPlatform);
        $suite = $document->createElement('test-suite');
        foreach ([
            'type' => 'TestSuite',
            'name' => $suiteName,
            'fullname' => $suiteName,
            'classname' => 'unity-command.tests',
            'testcasecount' => '1',
            'result' => 'Failed',
            'start-time' => gmdate(DATE_W3C),
            'duration' => '0',
            'total' => '1',
            'passed' => '0',
            'failed' => '1',
            'inconclusive' => '0',
            'skipped' => '0',
            'asserts' => '0'
        ] as $name => $value) {
            $suite->setAttribute($name, $value);
        }

        $properties = $document->createElement('properties');
        $platformProperty = $document->createElement('property');
        $platformProperty->setAttribute('name', 'unity-test-platform');
        $platformProperty->setAttribute('value', $testPlatform);
        $properties->appendChild($platformProperty);
        $exitCode = $executionError?->getExitCode() ?? $process?->getExitCode();
        if ($exitCode !== null) {
            $exitCodeProperty = $document->createElement('property');
            $exitCodeProperty->setAttribute('name', 'unity-process.exit-code');
            $exitCodeProperty->setAttribute('value', (string) $exitCode);
            $properties->appendChild($exitCodeProperty);
        }
        $suite->appendChild($properties);

        $testCase = $document->createElement('test-case');
        $testCase->setAttribute('name', $suiteName);
        $testCase->setAttribute('classname', 'unity-command.tests');
        $testCase->setAttribute('result', 'Failed');
        $testCase->setAttribute('label', 'UnityProcessError');
        $testCase->setAttribute('duration', '0');
        $failure = $document->createElement('failure');
        $message = $document->createElement('message');
        $message->textContent = sprintf("Unity test mode '%s' did not produce a usable test report.", $testPlatform);
        $failure->appendChild($message);
        $details = [$reportError];
        if ($executionError !== null) {
            $details[] = $executionError->getMessage();
        }
        $standardError = $executionError?->getStdErr() ?? $process?->getErrorOutput() ?? '';
        if ($standardError !== '') {
            $details[] = $standardError;
        }
        $stackTrace = $document->createElement('stack-trace');
        $stackTrace->textContent = implode(PHP_EOL . PHP_EOL, array_filter($details));
        $failure->appendChild($stackTrace);
        $testCase->appendChild($failure);
        $standardOutput = $executionError?->getStdOut() ?? $process?->getOutput() ?? '';
        if ($standardOutput !== '') {
            $output = $document->createElement('output');
            $output->textContent = $standardOutput;
            $testCase->appendChild($output);
        }
        $suite->appendChild($testCase);
        $root->appendChild($suite);
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
