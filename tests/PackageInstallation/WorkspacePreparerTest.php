<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WorkspacePreparerTest extends TestCase {
    private string $directory;

    protected function setUp(): void {
        $this->directory = temp_dir(__METHOD__);
    }

    /**
     * @dataProvider initializableWorkspaceProvider
     */
    public function testInitializesMissingAndEmptyWorkspaces(string $kind, WorkspaceState $expectedState): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . $kind;
        if ($expectedState === WorkspaceState::EMPTY) {
            mkdir($path);
        }
        $initializer = $this->createInitializer(function (string $workspace): void {
            $this->createProjectMarkers($workspace);
        });
        $preparer = new WorkspacePreparer(new WorkspaceClassifier(), $initializer);

        $actual = $preparer->prepare($path);

        $this->assertSame([$path], $initializer->paths);
        $this->assertSame($path, $actual->getPath());
        $this->assertSame($expectedState, $actual->getInitialState());
        $this->assertTrue($actual->wasInitialized());
    }

    public function initializableWorkspaceProvider(): iterable {
        yield 'missing' => [
            'missing',
            WorkspaceState::MISSING
        ];
        yield 'empty' => [
            'empty',
            WorkspaceState::EMPTY
        ];
    }

    public function testReusesValidProjectWithoutCallingInitializer(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'project';
        $this->createProjectMarkers($path);
        $initializer = $this->createInitializer(function (): void {
            $this->fail('Existing projects must not be initialized again.');
        });
        $preparer = new WorkspacePreparer(new WorkspaceClassifier(), $initializer);

        $actual = $preparer->prepare($path);

        $this->assertSame([], $initializer->paths);
        $this->assertSame(WorkspaceState::VALID_PROJECT, $actual->getInitialState());
        $this->assertFalse($actual->wasInitialized());
    }

    public function testRejectsInvalidWorkspaceBeforeCallingInitializer(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'invalid';
        mkdir($path);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'preserved.txt', 'preserved');
        $initializer = $this->createInitializer(function (): void {
            $this->fail('Invalid workspaces must not be initialized.');
        });
        $preparer = new WorkspacePreparer(new WorkspaceClassifier(), $initializer);

        try {
            $preparer->prepare($path);
            $this->fail('Invalid workspace should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame([], $initializer->paths);
            $this->assertSame('preserved', file_get_contents($path . DIRECTORY_SEPARATOR . 'preserved.txt'));
        }
    }

    public function testRejectsInitializerThatDoesNotCreateExactProjectRoot(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'workspace';
        $initializer = $this->createInitializer(function (string $workspace): void {
            mkdir($workspace);
            $this->createProjectMarkers($workspace . DIRECTORY_SEPARATOR . 'child');
        });
        $preparer = new WorkspacePreparer(new WorkspaceClassifier(), $initializer);

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('did not create a valid Unity project at exact path');

        $preparer->prepare($path);
    }

    public function testWrapsInitializerFailure(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'workspace';
        $initializer = $this->createInitializer(function (): void {
            throw new RuntimeException('Unity failed');
        });
        $preparer = new WorkspacePreparer(new WorkspaceClassifier(), $initializer);

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage("Unable to initialize Unity workspace '$path': Unity failed");

        $preparer->prepare($path);
    }

    private function createInitializer(Closure $callback): WorkspaceInitializerInterface {
        return new class($callback) implements WorkspaceInitializerInterface {
            public array $paths = [];
            private Closure $callback;

            public function __construct(Closure $callback) {
                $this->callback = $callback;
            }

            public function initialize(string $workspacePath): void {
                $this->paths[] = $workspacePath;
                ($this->callback)($workspacePath);
            }
        };
    }

    private function createProjectMarkers(string $path): void {
        if (! is_dir($path . DIRECTORY_SEPARATOR . 'ProjectSettings')) {
            mkdir($path . DIRECTORY_SEPARATOR . 'ProjectSettings', 0777, true);
        }
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt', "m_EditorVersion: 6000.0.0f1\n");
        file_put_contents($path . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset', "PlayerSettings:\n  productName: Test\n");
    }
}
