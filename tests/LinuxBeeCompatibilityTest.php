<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class LinuxBeeCompatibilityTest extends TestCase {
    private string $directory;
    private string $executable;
    private string $bee;

    protected function setUp(): void {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('The compatibility preparation is Linux-only.');
        }
        $this->directory = sys_get_temp_dir() . '/bee compatibility ' . bin2hex(random_bytes(8));
        mkdir($this->directory . '/Editor/Data', 0777, true);
        $this->executable = $this->directory . '/Editor/Unity';
        $this->bee = $this->directory . '/Editor/Data/bee_backend';
        copy(PHP_BINARY, $this->bee);
        chmod($this->bee, 0755);
    }

    protected function tearDown(): void {
        if (isset($this->directory)) {
            chmod($this->directory . '/Editor/Data', 0755);
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testPreparesOnceAndPreservesArgumentsAndFailure(): void {
        $original = file_get_contents($this->bee);
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        $wrapper = file_get_contents($this->bee);
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        $this->assertSame($wrapper, file_get_contents($this->bee));
        $this->assertSame($original, file_get_contents($this->bee . '.unity-original'));
        $process = new Process([$this->bee, '-r', 'echo json_encode(array_slice($argv, 1)); exit(17);',
            '--', '', 'path with spaces', '--stdin-canary', '--stdin-canary=1', '*', '--stdin-canary']);
        $this->assertSame(17, $process->run());
        $this->assertSame(['', 'path with spaces', '--stdin-canary=1', '*'], json_decode($process->getOutput(), true));
    }

    public function testDoesNotChangeOtherEditorLines(): void {
        $original = file_get_contents($this->bee);
        foreach (['2020.3.49f1', '2022.1.25f1', '2022.3.62f3', '6000.0.81f1'] as $version) {
            LinuxBeeCompatibility::prepare($version, $this->executable);
        }
        $this->assertSame($original, file_get_contents($this->bee));
        $this->assertFileDoesNotExist($this->bee . '.unity-original');
    }

    public function testConcurrentPreparationPreservesTheOriginal(): void {
        $source = (new \ReflectionClass(LinuxBeeCompatibility::class))->getFileName();
        $original = file_get_contents($this->bee);
        $processes = [];
        for ($i = 0; $i < 6; $i ++) {
            $process = new Process([PHP_BINARY, '-r',
                'require $argv[1]; Slothsoft\\Unity\\LinuxBeeCompatibility::prepare("2021.3.45f2", $argv[2]);',
                $source, $this->executable]);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $this->assertSame(0, $process->wait(), $process->getErrorOutput());
        }
        $this->assertSame($original, file_get_contents($this->bee . '.unity-original'));
    }

    public function testRecognizesHubReplacementAndPreservesTheNewBinary(): void {
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        copy('/bin/true', $this->bee);
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        $this->assertSame(file_get_contents('/bin/true'), file_get_contents($this->bee . '.unity-original'));
        $this->assertSame(0, (new Process([$this->bee, '--stdin-canary']))->run());
    }

    public function testRejectsUnknownBackupWithoutOverwritingIt(): void {
        file_put_contents($this->bee . '.unity-original', 'unrecognized backup');
        try {
            LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
            $this->fail('Expected an unknown backup to be rejected.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('Unrecognized Bee backup', $error->getMessage());
        }
        $this->assertSame('unrecognized backup', file_get_contents($this->bee . '.unity-original'));
        $this->assertSame(file_get_contents(PHP_BINARY), file_get_contents($this->bee));
    }

    public function testResumesPreparationInterruptedAfterSavingOriginal(): void {
        copy($this->bee, $this->bee . '.unity-original');
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        $this->assertSame(file_get_contents(PHP_BINARY), file_get_contents($this->bee . '.unity-original'));
        $this->assertSame(0, (new Process([$this->bee, '-r', 'exit(0);', '--stdin-canary']))->run());
    }

    public function testRejectsUnknownWrapper(): void {
        file_put_contents($this->bee, "#!/bin/sh\nexit 0\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unrecognized Bee replacement');
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
    }

    public function testPreparedReadOnlyCacheNeedsNoWrites(): void {
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        unlink($this->bee . '.unity-lock');
        chmod(dirname($this->bee), 0555);
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        $this->assertFileDoesNotExist($this->bee . '.unity-lock');
    }

    public function testRejectsTamperedOriginal(): void {
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
        copy('/bin/true', $this->bee . '.unity-original');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unrecognized Bee backup');
        LinuxBeeCompatibility::prepare('2021.3.45f2', $this->executable);
    }
}
