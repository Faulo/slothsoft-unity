<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\ServerEnvironment;
use Slothsoft\FarahTesting\TestUtils;
use Symfony\Component\Process\Process;

/**
 * UnityEditorTest
 *
 * @see UnityEditor
 */
final class UnityEditorTest extends TestCase {

    public static function setUpBeforeClass(): void {
        TestUtils::changeWorkingDirectoryToComposerRoot();
    }
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityEditor::class), "Failed to load class 'Slothsoft\Unity\UnityEditor'!");
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstallModulesSkipsModulesThatAreAlreadyInstalled(): void {
        [$editor, $locator] = $this->createSyntheticEditor([
            'webgl' => true
        ], [], 42);

        $this->assertTrue($editor->installModules('webgl'));
        $this->assertSame(0, $locator->executionCount);
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstallModulesVerifiesInstalledModulesInsteadOfExitCode(): void {
        [$editor, $locator] = $this->createSyntheticEditor([
            'webgl' => false
        ], [
            'webgl'
        ], 1);

        $this->assertTrue($editor->installModules('webgl'));
        $this->assertSame(1, $locator->executionCount);
        $this->assertSame([
            'install-modules',
            '--version',
            '6000.3.13f1',
            '--childModules',
            '--module',
            'webgl'
        ], $locator->arguments);
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstallModulesOnlyRequestsMissingModules(): void {
        [$editor, $locator] = $this->createSyntheticEditor([
            'webgl' => true,
            'linux-il2cpp' => false
        ], [
            'linux-il2cpp'
        ], 0);

        $this->assertTrue($editor->installModules('webgl', 'linux-il2cpp'));
        $this->assertSame([
            'install-modules',
            '--version',
            '6000.3.13f1',
            '--childModules',
            '--module',
            'linux-il2cpp'
        ], $locator->arguments);
        $this->assertSame([
            'webgl',
            'linux-il2cpp'
        ], $editor->getInstalledModules());
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstallModulesPreservesFailureWhenModuleIsStillMissing(): void {
        [$editor] = $this->createSyntheticEditor([
            'webgl' => false
        ], [], 42);

        try {
            $editor->installModules('webgl');
            $this->fail('Expected the failed module installation to be propagated.');
        } catch (ExecutionError $error) {
            $this->assertSame(42, $error->getExitCode());
        }
    }

    /**
     * @runInSeparateProcess
     */
    public function testInstallModulesRejectsSuccessfulProcessWithoutInstalledModule(): void {
        [$editor] = $this->createSyntheticEditor([
            'webgl' => false
        ], [], 0);

        $this->expectException(ExecutionError::class);
        $this->expectExceptionMessage('Unity Hub finished without installing the requested modules: webgl.');

        $editor->installModules('webgl');
    }
    
    public function testCreateEmptyProject(): void {
        $hub = UnityHub::getInstance();
        
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
        }
        
        $editors = $hub->getEditors();
        if ($editors === []) {
            $this->markTestSkipped('Please provide at least one installed Unity Editor.');
        }
        // Keep this smoke test aligned with the agent instead of installing an obsolete editor.
        usort($editors, static fn (UnityEditor $left, UnityEditor $right): int => version_compare($left->version, $right->version));
        $editor = array_pop($editors);
        $this->assertInstanceOf(UnityEditor::class, $editor);
        
        $target = ServerEnvironment::getCacheDirectory() . DIRECTORY_SEPARATOR . 'EmptyProject';
        
        FileSystem::removeDir($target, true);
        FileSystem::ensureDirectory($target);
        
        $editor->createEmptyProject($target, false);
        
        $result = $editor->execute(true, '-projectPath', $target, '-quit');
        
        $this->assertThat($result->getExitCode(), new IsEqual(0));
    }

    private function createSyntheticEditor(array $modules, array $modulesToInstall, int $exitCode): array {
        $editorDirectory = temp_dir(str_replace(':', '-', __METHOD__)) . DIRECTORY_SEPARATOR . '6000.3.13f1';
        $executableDirectory = $editorDirectory . DIRECTORY_SEPARATOR . 'Editor';
        FileSystem::ensureDirectory($executableDirectory);
        $executable = $executableDirectory . DIRECTORY_SEPARATOR . 'Unity';
        touch($executable);

        $manifest = [];
        foreach ($modules as $id => $selected) {
            $manifest[] = [
                'id' => $id,
                'selected' => $selected
            ];
        }
        JsonUtils::save($editorDirectory . DIRECTORY_SEPARATOR . 'modules.json', $manifest);

        $locator = new SyntheticModuleInstallHubLocator(
            $editorDirectory . DIRECTORY_SEPARATOR . 'modules.json',
            $modulesToInstall,
            $exitCode
        );
        UnityHub::setHubLocator($locator);
        $editor = new UnityEditor(UnityHub::getInstance(), '6000.3.13f1');
        $editor->setExecutable($executable);

        return [$editor, $locator];
    }
}

final class SyntheticModuleInstallHubLocator implements HubLocatorInterface {

    public int $executionCount = 0;

    public array $arguments = [];

    public function __construct(
        private readonly string $manifest,
        private readonly array $modulesToInstall,
        private readonly int $exitCode
    ) {
    }

    public function create(array $arguments): Process {
        $this->executionCount ++;
        $this->arguments = $arguments;
        return new Process([
            PHP_BINARY,
            __DIR__ . '/../test-files/Command/install-modules.php',
            $this->manifest,
            json_encode($this->modulesToInstall, JSON_THROW_ON_ERROR),
            (string) $this->exitCode
        ]);
    }

    public function exists(): bool {
        return true;
    }
}
