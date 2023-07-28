<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Slothsoft\Core\FileSystem;

class ScriptsTest extends TestCase {

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
        yield 'unity-build' => [
            'unity-build'
        ];
        yield 'unity-method' => [
            'unity-method'
        ];
        yield 'unity-documentation' => [
            'unity-documentation'
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

        $this->assertEquals('', $errors, "Retrieving '$url' failed! Command:" . PHP_EOL . $process->getCommandLine());

        $this->assertEquals(0, $code, "Retrieving '$url' failed! Command:" . PHP_EOL . $process->getCommandLine());
    }

    public function validAssets(): iterable {
        yield 'unity-hub-help' => [
            'farah://slothsoft@unity/hub/help'
        ];
    }
}