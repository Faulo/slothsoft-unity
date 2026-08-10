<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\FarahTesting\TestUtils;
use Slothsoft\Unity\Command\Reporting\JUnitReportValidator;
use Symfony\Component\Process\Process;

class ScriptsTest extends TestCase {
    
    public static function setUpBeforeClass(): void {
        TestUtils::changeWorkingDirectoryToComposerRoot();
    }
    
    /**
     *
     * @dataProvider validBinaries
     */
    public function testUnityTests(string $script, array $args = []): void {
        $process = new Process([
            PHP_BINARY,
            "scripts/$script",
            ...$args
        ]);
        
        $code = $process->run();
        $result = $process->getOutput();
        $errors = $process->getErrorOutput();
        
        $this->assertEquals('', $errors, "Calling $script failed! Command:" . PHP_EOL . $process->getCommandLine());
        
        $this->assertEquals(0, $code, "Calling $script failed! Command:" . PHP_EOL . $process->getCommandLine());
        
        $this->assertStringContainsString("composer exec $script", $result, "Calling $script failed! Command:" . PHP_EOL . $process->getCommandLine());
    }
    
    public function validBinaries(): iterable {
        yield 'autoversion' => [
            'autoversion'
        ];
        yield 'steam-buildfile' => [
            'steam-buildfile'
        ];
        yield 'steam-login' => [
            'steam-login',
            [
                'help'
            ]
        ];
        yield 'transform-dotnet-format' => [
            'transform-dotnet-format'
        ];
        yield 'unity-build' => [
            'unity-build'
        ];
        yield 'unity-method' => [
            'unity-method'
        ];
        yield 'unity-start' => [
            'unity-start'
        ];
        yield 'unity-documentation' => [
            'unity-documentation'
        ];
        yield 'unity-empty-project' => [
            'unity-empty-project'
        ];
        yield 'unity-package-install' => [
            'unity-package-install'
        ];
        yield 'unity-tests' => [
            'unity-tests'
        ];
        yield 'unity-help' => [
            'unity-help',
            [
                ''
            ]
        ];
        yield 'unity-module-install' => [
            'unity-module-install'
        ];
        yield 'unity-project-version' => [
            'unity-project-version'
        ];
        yield 'unity-project-setting' => [
            'unity-project-setting'
        ];
    }
    
    /**
     *
     * @dataProvider validAssets
     */
    public function testUnityAssets(string $url): void {
        if (! FileSystem::commandExists('composer')) {
            $this->markTestSkipped('Composer is not available from the command line!');
            return;
        }
        
        $process = new Process([
            'composer',
            'exec',
            'farah-asset',
            $url
        ]);
        
        $code = $process->run();
        $errors = $process->getErrorOutput();
        
        if (PHP_OS_FAMILY === 'Windows') {
            $this->assertEquals('', $errors, "Retrieving '$url' failed! Command:" . PHP_EOL . $process->getCommandLine());
        }
        
        $this->assertEquals(0, $code, "Retrieving '$url' failed! Command:" . PHP_EOL . $process->getCommandLine());
    }
    
    public function validAssets(): iterable {
        yield 'unity-hub-help' => [
            'farah://slothsoft@unity/hub/help'
        ];
    }

    public function testUnityCommandHelp(): void {
        $process = new Process([
            PHP_BINARY,
            'scripts/unity-command',
            'help'
        ]);

        $code = $process->run();

        $this->assertSame(0, $code, $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $this->assertStringContainsString('Usage:', $process->getOutput());
        $this->assertStringContainsString('help [options] [--] [<command_name>]', $process->getOutput());
    }
    
    public function testComposerUnityCommandListsEveryOperation(): void {
        $process = new Process([
            'composer',
            'exec',
            'unity-command',
            '--',
            'list',
            '--raw'
        ]);
        
        $code = $process->run();
        
        $this->assertSame(0, $code, $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        foreach ([
            'build',
            'empty-project',
            'method',
            'start',
            'module-install',
            'package-install',
            'tests'
        ] as $command) {
            $this->assertMatchesRegularExpression(sprintf('/^%s\s/m', preg_quote($command, '/')), $process->getOutput());
        }
    }

    /**
     * @dataProvider composerPackageInstallModes
     */
    public function testComposerUnityCommandRunsPackageInstallEndToEnd(string $mode): void {
        $directory = temp_dir(str_replace(':', '-', __METHOD__) . '-' . $mode);
        $workspace = $directory . DIRECTORY_SEPARATOR . 'workspace';
        $package = $directory . DIRECTORY_SEPARATOR . 'package';
        $this->createComposerTestProject($workspace);
        $this->createComposerTestPackage($package);

        $arguments = [
            'composer',
            'exec',
            'unity-command',
            '--',
            'package-install'
        ];
        $reportPath = $directory . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'package-install.xml';
        if ($mode === 'file') {
            $arguments[] = '--junit';
            $arguments[] = $reportPath;
        } elseif ($mode === 'stdout') {
            $arguments[] = '--junit';
            $arguments[] = '-';
        }
        $arguments[] = $workspace;
        $arguments[] = $package;

        $process = new Process($arguments);
        $code = $process->run();

        $this->assertSame(0, $code, $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput());
        $destination = $workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        $this->assertSame('installed payload', file_get_contents($destination . DIRECTORY_SEPARATOR . 'payload.txt'));
        $manifest = json_decode(file_get_contents($workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('1.0.0', $manifest['dependencies']['existing.package']);
        $this->assertSame('1.1.33', $manifest['dependencies']['com.unity.test-framework']);

        if ($mode === 'normal') {
            $this->assertSame('', $process->getOutput(), 'Normal mode must not print the internal Farah result XML.');
        } elseif ($mode === 'file') {
            $this->assertSame('', $process->getOutput(), 'File reporting must preserve normal output and not print report XML.');
            $this->assertFileExists($reportPath);
            $this->assertValidJUnitXml(file_get_contents($reportPath));
        } else {
            $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $process->getOutput());
            $this->assertValidJUnitXml($process->getOutput());
        }
    }

    public function composerPackageInstallModes(): iterable {
        yield 'normal output' => ['normal'];
        yield 'file JUnit' => ['file'];
        yield 'stdout JUnit' => ['stdout'];
    }

    private function createComposerTestProject(string $workspace): void {
        mkdir($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings', 0777, true);
        mkdir($workspace . DIRECTORY_SEPARATOR . 'Packages');
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt', "m_EditorVersion: 2022.1.0f1\n");
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset', "PlayerSettings:\n  productName: Existing Project\n");
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json', json_encode([
            'dependencies' => [
                'existing.package' => '1.0.0'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    private function createComposerTestPackage(string $package): void {
        mkdir($package);
        file_put_contents($package . DIRECTORY_SEPARATOR . 'package.json', json_encode([
            'name' => 'com.example.package',
            'version' => '1.0.0'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        file_put_contents($package . DIRECTORY_SEPARATOR . 'payload.txt', 'installed payload');
    }

    private function assertValidJUnitXml(string $xml): void {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        (new JUnitReportValidator())->assertValid($document);
    }
}
