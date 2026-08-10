<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class WorkspaceClassifierTest extends TestCase {
    private WorkspaceClassifier $classifier;
    private string $directory;

    protected function setUp(): void {
        $this->classifier = new WorkspaceClassifier();
        $this->directory = temp_dir(str_replace(':', '-', __METHOD__));
    }

    public function testClassifiesMissingPath(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'missing';

        $actual = $this->classifier->classify($path);

        $this->assertSame($path, $actual->getPath());
        $this->assertSame(WorkspaceState::MISSING, $actual->getState());
        $this->assertTrue($actual->isInitializable());
    }

    public function testClassifiesEmptyDirectory(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'empty';
        mkdir($path);

        $actual = $this->classifier->classify($path);

        $this->assertSame(WorkspaceState::EMPTY, $actual->getState());
        $this->assertTrue($actual->isInitializable());
    }

    public function testClassifiesExactUnityProjectRoot(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'project';
        $this->createProjectMarkers($path);

        $actual = $this->classifier->classify($path);

        $this->assertSame(WorkspaceState::VALID_PROJECT, $actual->getState());
        $this->assertFalse($actual->isInitializable());
    }

    public function testRejectsNonEmptyDirectoryWithoutProjectMarkers(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'invalid';
        mkdir($path);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');

        $actual = $this->classifier->classify($path);

        $this->assertSame(WorkspaceState::INVALID, $actual->getState());
        $this->assertFalse($actual->isInitializable());
    }

    public function testDoesNotDiscoverProjectInChildDirectory(): void {
        $workspace = $this->directory . DIRECTORY_SEPARATOR . 'workspace';
        mkdir($workspace);
        $this->createProjectMarkers($workspace . DIRECTORY_SEPARATOR . 'child');

        $actual = $this->classifier->classify($workspace);

        $this->assertSame(WorkspaceState::INVALID, $actual->getState());
    }

    public function testDoesNotDiscoverProjectInParentDirectory(): void {
        $project = $this->directory . DIRECTORY_SEPARATOR . 'project';
        $this->createProjectMarkers($project);
        $workspace = $project . DIRECTORY_SEPARATOR . 'nested';
        mkdir($workspace);
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'keep.txt', 'keep');

        $actual = $this->classifier->classify($workspace);

        $this->assertSame(WorkspaceState::INVALID, $actual->getState());
    }

    public function testTreatsExistingFileAsInvalidWorkspace(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'workspace';
        file_put_contents($path, 'not a directory');

        $actual = $this->classifier->classify($path);

        $this->assertSame(WorkspaceState::INVALID, $actual->getState());
    }

    public function testRejectsSymlinkToUnityProjectRoot(): void {
        $project = $this->directory . DIRECTORY_SEPARATOR . 'project';
        $this->createProjectMarkers($project);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'workspace-link';
        if (! @symlink($project, $path)) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }

        try {
            $actual = $this->classifier->classify($path);
            $this->assertSame(WorkspaceState::INVALID, $actual->getState());
        } finally {
            PHP_OS_FAMILY === 'Windows' ? @rmdir($path) : @unlink($path);
        }
    }

    public function testRejectsWindowsJunctionToUnityProjectRoot(): void {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Windows junction behavior is platform-specific.');
        }

        $project = $this->directory . DIRECTORY_SEPARATOR . 'project';
        $this->createProjectMarkers($project);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'workspace-junction';
        $process = new Process([
            'cmd.exe',
            '/d',
            '/c',
            'mklink',
            '/J',
            $path,
            $project
        ]);
        if ($process->run() !== 0) {
            $this->markTestSkipped('Unable to create a Windows junction: ' . $process->getErrorOutput());
        }

        try {
            $actual = $this->classifier->classify($path);
            $this->assertSame(WorkspaceState::INVALID, $actual->getState());
        } finally {
            @rmdir($path);
        }
    }

    private function createProjectMarkers(string $path): void {
        mkdir($path . DIRECTORY_SEPARATOR . 'ProjectSettings', 0777, true);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt', "m_EditorVersion: 6000.0.0f1\n");
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset', "PlayerSettings:\n  productName: Test\n");
    }
}
