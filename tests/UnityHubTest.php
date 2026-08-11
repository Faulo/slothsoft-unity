<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\Command\SymfonyProcessOutputHandler;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

class UnityHubTest extends TestCase {
    
    public function testClassExists() {
        $this->assertTrue(class_exists(UnityHub::class));
    }
    
    public function testLoggingEnabled() {
        foreach ([
                     true,
                     false
                 ] as $value) {
            UnityHub::setLoggingEnabled($value);
            $this->assertEquals($value, UnityHub::getLoggingEnabled());
        }
    }
    
    public function testThrowOnFailure() {
        foreach ([
                     true,
                     false
                 ] as $value) {
            UnityHub::setThrowOnFailure($value);
            $this->assertEquals($value, UnityHub::getThrowOnFailure());
        }
    }
    
    public function testProcessTimeout() {
        try {
            foreach ([
                         0,
                         60
                     ] as $value) {
                UnityHub::setProcessTimeout($value);
                $this->assertEquals($value, UnityHub::getProcessTimeout());
            }
        } finally {
            UnityHub::setProcessTimeout(0);
        }
    }
    
    public function testConfigSnapshot(): void {
        $previousConfig = UnityHub::getConfig();
        $output = new BufferedOutput();
        $handler = new SymfonyProcessOutputHandler($output, $output);
        $config = UnityHub::getConfig();
        $config->loggingEnabled = true;
        $config->throwOnFailure = true;
        $config->propagateProcessExitCodes = true;
        $config->processTimeout = 60;
        $config->processOutputHandler = $handler;

        try {
            UnityHub::setConfig($config);

            $this->assertTrue(UnityHub::getLoggingEnabled());
            $this->assertTrue(UnityHub::getThrowOnFailure());
            $this->assertTrue(UnityHub::getPropagateProcessExitCodes());
            $this->assertSame(60, UnityHub::getProcessTimeout());
            $this->assertSame($handler, UnityHub::getProcessOutputHandler());
        } finally {
            UnityHub::setConfig($previousConfig);
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testHubExecutionValidationUsesCommandScopedPropagationConfig(): void {
        $previousLocator = UnityHub::getHubLocator();
        $previousConfig = UnityHub::getConfig();
        UnityHub::setHubLocator(new SyntheticHubLocator(42));
        $config = clone $previousConfig;
        $sink = new BufferedOutput();
        $config->processOutputHandler = new SymfonyProcessOutputHandler($sink, $sink);
        UnityHub::setConfig($config);

        try {
            $config = UnityHub::getConfig();
            $config->throwOnFailure = true;
            $config->propagateProcessExitCodes = false;
            UnityHub::setConfig($config);
            $process = UnityHub::getInstance()->execute('ignored');
            $this->assertSame(42, $process->getExitCode());

            $config->propagateProcessExitCodes = true;
            UnityHub::setConfig($config);
            try {
                UnityHub::getInstance()->execute('ignored');
                $this->fail('Expected a non-zero Hub exit code to be propagated.');
            } catch (ExecutionError $error) {
                $this->assertSame(42, $error->getExitCode());
            }
        } finally {
            UnityHub::setHubLocator($previousLocator);
            UnityHub::setConfig($previousConfig);
        }
    }

    public function testHubIsInstalled(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        $result = $hub->execute('help');
        $ouput = trim($result->getOutput());
        $this->assertNotEquals('', $ouput);
        $this->assertStringContainsString('editors', $ouput);
    }
    
    public function testExecute(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $result = $hub->execute('install-path', '--get');
        $ouput = trim($result->getOutput());
        $this->assertNotEquals('', $ouput);
        $this->assertDirectoryExists($ouput);
    }
    
    public function testGetEditors(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $editors = $hub->getEditors();
        $this->assertIsArray($editors);
        foreach ($editors as $version => $editor) {
            $this->assertEditorIsValid($editor, $version);
        }
    }
    
    private function assertEditorIsValid(UnityEditor $editor, string $version) {
        $this->assertInstanceOf(UnityEditor::class, $editor);
        $this->assertTrue($editor->isInstalled());
        $this->assertStringContainsString($version, $editor->executable);
    }
    
    public function testGetEditorPath(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $path = $hub->getEditorPath();
        $this->assertDirectoryExists($path);
    }
    
    public function testGetEditorByVersion(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $editors = $hub->getEditors();
        if (count($editors) === 0) {
            $this->markTestSkipped('Needs at least 1 installed editor to test getEditorByVersion.');
            return;
        }
        
        $editor = array_shift($editors);
        $version = $editor->version;
        $editor = $hub->getEditorByVersion($version);
        $this->assertEditorIsValid($editor, $version);
    }
    
    /**
     *
     * @dataProvider validUnityVersions
     */
    public function testCreateEditorInstallation(string $version) {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $this->assertIsArray($hub->createEditorInstallation($version));
    }
    
    public function validUnityVersions(): iterable {
        yield '2019.4.17f1' => [
            '2019.4.17f1'
        ];
        yield '2022.2.0a12' => [
            '2022.2.0a12'
        ];
        yield '2022.2.0b1' => [
            '2022.2.0b1'
        ];
        yield '6000.0.40f1' => [
            '6000.0.40f1'
        ];
    }
    
    const VALID_LICENSE_DIRECTORY = __DIR__ . '/../test-files/ValidLicenses';
    
    public function testFindLicenses(): void {
        $licenseFolder = self::VALID_LICENSE_DIRECTORY;
        $licenseFile = realpath(self::VALID_LICENSE_DIRECTORY . DIRECTORY_SEPARATOR . 'Unity_v2022.x.ulf');
        
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        UnityHub::addLicenseFolder($licenseFolder);
        
        $this->assertEquals([
            $licenseFile
        ], iterator_to_array($hub->findLicenses('2022.1.4')));
    }
    
    const VALID_PACKAGE_DIRECTORY = __DIR__ . '/../test-files/ValidPackage';
    
    public function testFindPackage(): void {
        $packageFolder = self::VALID_PACKAGE_DIRECTORY;
        
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        
        $package = $hub->findPackage($packageFolder);
        
        $this->assertNotNull($package, "Failed to find package!");
    }
    
    /**
     * @runInSeparateProcess
     * @dataProvider editorVersions
     */
    public function testInventStableEditorVersion(string $requestedVersion, bool $highest, string $expectedVersion): void {
        $hub = UnityHub::getInstance();
        $releaseApi = new \ReflectionProperty($hub, 'releaseApi');
        $releaseApi->setValue($hub, new UnityReleaseApi(static fn (): string => '{"total":0,"results":[]}'));
        $changesets = new \ReflectionProperty($hub, 'changesets');
        $changesets->setValue($hub, array_fill_keys([
            '2022.3.10f1',
            '2022.3.20f1',
            '6000.0.40f1',
            '6000.0.60f1',
            '6000.1.12f1',
            '6000.2.0b1'
        ], null));
        
        $actualVersion = $hub->inventStableEditorVersion($requestedVersion, $highest);
        
        $this->assertEquals($expectedVersion, $actualVersion);
    }

    /**
     * @runInSeparateProcess
     */
    public function testInventChangesetUsesExactReleaseApiResult(): void {
        $hub = UnityHub::getInstance();
        $changesets = new \ReflectionProperty($hub, 'changesets');
        $changesets->setValue($hub, []);
        $releaseApi = new \ReflectionProperty($hub, 'releaseApi');
        $releaseApi->setValue($hub, new UnityReleaseApi(static fn (): string => json_encode([
            'total' => 2,
            'results' => [
                [
                    'version' => '2019.4.41f1',
                    'shortRevision' => 'fb553f8fdd6c'
                ],
                [
                    'version' => '2019.4.41f2',
                    'shortRevision' => '6b23d448b533'
                ]
            ]
        ], JSON_THROW_ON_ERROR)));

        $this->assertSame('6b23d448b533', $hub->inventChangeset('2019.4.41f2'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testInventStableEditorVersionIncludesReleaseApiResults(): void {
        $hub = UnityHub::getInstance();
        $changesets = new \ReflectionProperty($hub, 'changesets');
        $changesets->setValue($hub, [
            '2019.4.40f1' => null
        ]);
        $releaseApi = new \ReflectionProperty($hub, 'releaseApi');
        $releaseApi->setValue($hub, new UnityReleaseApi(static fn (): string => json_encode([
            'total' => 1,
            'results' => [
                [
                    'version' => '2019.4.41f2',
                    'shortRevision' => '6b23d448b533'
                ]
            ]
        ], JSON_THROW_ON_ERROR)));

        $this->assertSame('2019.4.41f2', $hub->inventStableEditorVersion('2019', true));
    }
    
    public function editorVersions(): iterable {
        yield 'major' => [
            '6000',
            true,
            '6000.1.12f1'
        ];
        yield 'minor' => [
            '6000.0',
            true,
            '6000.0.60f1'
        ];
        yield 'patch' => [
            '6000.0.40',
            true,
            '6000.0.40f1'
        ];
        yield 'exact' => [
            '6000.0.40f1',
            true,
            '6000.0.40f1'
        ];
        yield 'latest final release' => [
            '2022.3',
            true,
            '2022.3.20f1'
        ];
        yield 'latest overall' => [
            '',
            true,
            '6000.1.12f1'
        ];
        yield 'minimum package version' => [
            '2022.3',
            false,
            '2022.3.10f1'
        ];
    }
}

final class SyntheticHubLocator implements HubLocatorInterface {

    public function __construct(private int $exitCode) {
    }

    public function create(array $arguments): Process {
        return new Process([
            PHP_BINARY,
            __DIR__ . '/../test-files/Command/process-output.php',
            (string) $this->exitCode
        ]);
    }

    public function exists(): bool {
        return true;
    }
}
