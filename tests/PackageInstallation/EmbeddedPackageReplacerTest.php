<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class EmbeddedPackageReplacerTest extends TestCase {
    private string $directory;
    private string $workspace;
    private string $source;

    protected function setUp(): void {
        $this->directory = temp_dir(__METHOD__);
        $this->workspace = $this->directory . DIRECTORY_SEPARATOR . 'workspace';
        $this->source = $this->directory . DIRECTORY_SEPARATOR . 'source-with-unrelated-name';
        $this->createProject($this->workspace);
        $this->createPackage($this->source, 'com.example.package');
    }

    public function testInstallsNewPackageAtDestinationDerivedFromMetadata(): void {
        mkdir($this->source . DIRECTORY_SEPARATOR . 'Runtime');
        file_put_contents($this->source . DIRECTORY_SEPARATOR . 'Runtime' . DIRECTORY_SEPARATOR . 'Package.cs', 'new content');
        file_put_contents($this->source . DIRECTORY_SEPARATOR . '.hidden', 'hidden content');

        $destination = (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);

        $expected = realpath($this->workspace . DIRECTORY_SEPARATOR . 'Packages') . DIRECTORY_SEPARATOR . 'com.example.package';
        $this->assertSame($expected, $destination);
        $this->assertSame('new content', file_get_contents($destination . DIRECTORY_SEPARATOR . 'Runtime' . DIRECTORY_SEPARATOR . 'Package.cs'));
        $this->assertSame('hidden content', file_get_contents($destination . DIRECTORY_SEPARATOR . '.hidden'));
        $this->assertFileExists($this->source . DIRECTORY_SEPARATOR . 'Runtime' . DIRECTORY_SEPARATOR . 'Package.cs');
        $this->assertNoStagingArtifacts();
    }

    public function testCompletelyReplacesExistingDestination(): void {
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        mkdir($destination . DIRECTORY_SEPARATOR . 'Old', 0777, true);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'Old' . DIRECTORY_SEPARATOR . 'obsolete.txt', 'obsolete');
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'package.json', '{"name":"com.example.package","old":true}');
        file_put_contents($this->source . DIRECTORY_SEPARATOR . 'new.txt', 'new');

        $actual = (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);

        $this->assertSame(realpath($destination), realpath($actual));
        $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'Old' . DIRECTORY_SEPARATOR . 'obsolete.txt');
        $this->assertDirectoryDoesNotExist($destination . DIRECTORY_SEPARATOR . 'Old');
        $this->assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'new.txt'));
        $this->assertSame([
            'name' => 'com.example.package'
        ], json_decode(file_get_contents($destination . DIRECTORY_SEPARATOR . 'package.json'), true, 512, JSON_THROW_ON_ERROR));
        $this->assertNoStagingArtifacts();
    }

    public function testReplacesRegularFileDestination(): void {
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        file_put_contents($destination, 'stale file');
        file_put_contents($this->source . DIRECTORY_SEPARATOR . 'new.txt', 'new');

        (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);

        $this->assertDirectoryExists($destination);
        $this->assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'new.txt'));
        $this->assertNoStagingArtifacts();
    }

    public function testRollbackRestoresExistingDestinationAfterActivation(): void {
        $destination = $this->createExistingDestination();
        file_put_contents($this->source . DIRECTORY_SEPARATOR . 'new.txt', 'new');
        $replacer = new EmbeddedPackageReplacer();

        $replacer->prepare($this->workspace, $this->source);
        $replacer->activate();
        $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'preserved.txt');
        $this->assertSame('new', file_get_contents($destination . DIRECTORY_SEPARATOR . 'new.txt'));

        $replacer->rollback();

        $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
        $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'new.txt');
        $this->assertNoStagingArtifacts();
    }

    public function testOldDestinationLinkIsRemovedWithoutTouchingTarget(): void {
        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-old-package';
        mkdir($outside);
        file_put_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        $destination = $this->createExistingDestination();
        if (! @symlink($outside, $destination . DIRECTORY_SEPARATOR . 'linked-directory')) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }

        (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);

        $this->assertSame('preserved', file_get_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt'));
        $this->assertDirectoryDoesNotExist($destination . DIRECTORY_SEPARATOR . 'linked-directory');
        $this->assertNoStagingArtifacts();
    }

    public function testWindowsJunctionInsideOldDestinationDoesNotEscapeDeletionRoot(): void {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows junction behavior is platform-specific.');
        }

        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-junction';
        mkdir($outside);
        file_put_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        $destination = $this->createExistingDestination();
        $junction = $destination . DIRECTORY_SEPARATOR . 'junction';
        $process = new Process([
            'cmd.exe',
            '/d',
            '/c',
            'mklink',
            '/J',
            $junction,
            $outside
        ]);
        if ($process->run() !== 0) {
            $this->markTestSkipped('Unable to create a Windows junction: ' . $process->getErrorOutput());
        }

        (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);

        $this->assertSame('preserved', file_get_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt'));
        $this->assertDirectoryDoesNotExist($junction);
        $this->assertNoStagingArtifacts();
    }

    public function testRejectsWindowsJunctionInsideSourceBeforeMovingDestination(): void {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows junction behavior is platform-specific.');
        }

        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-source-junction';
        mkdir($outside);
        file_put_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        $junction = $this->source . DIRECTORY_SEPARATOR . 'junction';
        $process = new Process([
            'cmd.exe',
            '/d',
            '/c',
            'mklink',
            '/J',
            $junction,
            $outside
        ]);
        if ($process->run() !== 0) {
            $this->markTestSkipped('Unable to create a Windows junction: ' . $process->getErrorOutput());
        }
        $destination = $this->createExistingDestination();

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);
            $this->fail('Source junctions should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        } finally {
            @rmdir($junction);
        }
    }

    public function testInvalidPackageFailsBeforeExistingDestinationIsMoved(): void {
        $destination = $this->createExistingDestination();
        file_put_contents($this->source . DIRECTORY_SEPARATOR . 'package.json', '{invalid');

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);
            $this->fail('Invalid package metadata should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        }
    }

    public function testStagedValidationFailureLeavesExistingDestinationUntouched(): void {
        $destination = $this->createExistingDestination();
        $reader = new class implements PackageMetadataReaderInterface {
            public int $calls = 0;
            private PackageMetadataReader $reader;

            public function __construct() {
                $this->reader = new PackageMetadataReader();
            }

            public function read(string $packagePath): PackageMetadata {
                $this->calls ++;
                if ($this->calls === 2) {
                    throw new PackageInstallationException('Staged validation failed.');
                }

                return $this->reader->read($packagePath);
            }
        };
        $replacer = new EmbeddedPackageReplacer(null, $reader);

        try {
            $replacer->replace($this->workspace, $this->source);
            $this->fail('Staged validation should fail.');
        } catch (PackageInstallationException $exception) {
            $this->assertSame('Staged validation failed.', $exception->getMessage());
            $this->assertSame(2, $reader->calls);
            $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        }
    }

    public function testInvalidWorkspaceIsNotModified(): void {
        $invalidWorkspace = $this->directory . DIRECTORY_SEPARATOR . 'invalid-workspace';
        mkdir($invalidWorkspace);
        file_put_contents($invalidWorkspace . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');

        try {
            (new EmbeddedPackageReplacer())->replace($invalidWorkspace, $this->source);
            $this->fail('Invalid workspace should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($invalidWorkspace . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertDirectoryDoesNotExist($invalidWorkspace . DIRECTORY_SEPARATOR . 'Packages');
        }
    }

    public function testRejectsSourceThatIsTheExistingDestination(): void {
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        $this->createPackage($destination, 'com.example.package');
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $destination);
            $this->fail('Overlapping source and destination should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        }
    }

    public function testRejectsPackagesDirectorySymlinkWithoutTouchingTarget(): void {
        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-packages';
        mkdir($outside);
        file_put_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        rmdir($this->workspace . DIRECTORY_SEPARATOR . 'Packages');
        if (! @symlink($outside, $this->workspace . DIRECTORY_SEPARATOR . 'Packages')) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);
            $this->fail('A symlinked Packages directory should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertFileDoesNotExist($outside . DIRECTORY_SEPARATOR . 'com.example.package');
        }
    }

    public function testRejectsDestinationSymlinkWithoutTouchingTarget(): void {
        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-destination';
        mkdir($outside);
        file_put_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        if (! @symlink($outside, $destination)) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);
            $this->fail('A symlinked destination should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('preserved', file_get_contents($outside . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        }
    }

    public function testRejectsSymlinkInsideSourceBeforeMovingDestination(): void {
        $outside = $this->directory . DIRECTORY_SEPARATOR . 'outside-file';
        file_put_contents($outside, 'outside');
        if (! @symlink($outside, $this->source . DIRECTORY_SEPARATOR . 'linked-file')) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }
        $destination = $this->createExistingDestination();

        try {
            (new EmbeddedPackageReplacer())->replace($this->workspace, $this->source);
            $this->fail('Source symlinks should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame('outside', file_get_contents($outside));
            $this->assertSame('preserved', file_get_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt'));
            $this->assertNoStagingArtifacts();
        }
    }

    private function createProject(string $path): void {
        mkdir($path . DIRECTORY_SEPARATOR . 'ProjectSettings', 0777, true);
        mkdir($path . DIRECTORY_SEPARATOR . 'Packages');
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt', "m_EditorVersion: 6000.0.0f1\n");
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset', "PlayerSettings:\n  productName: Test\n");
    }

    private function createPackage(string $path, string $name): void {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . 'package.json', json_encode([
            'name' => $name
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function createExistingDestination(): string {
        $destination = $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'com.example.package';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');

        return $destination;
    }

    private function assertNoStagingArtifacts(): void {
        $packages = $this->workspace . DIRECTORY_SEPARATOR . 'Packages';
        $this->assertSame([], glob($packages . DIRECTORY_SEPARATOR . '.com.example.package.install-*'));
        $this->assertSame([], glob($packages . DIRECTORY_SEPARATOR . '.com.example.package.backup-*'));
    }
}
