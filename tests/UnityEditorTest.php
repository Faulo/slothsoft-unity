<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\ServerEnvironment;
use Slothsoft\FarahTesting\TestUtils;

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
}
