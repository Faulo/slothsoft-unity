<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\ServerEnvironment;
use Slothsoft\FarahTesting\TestUtils;
use PHPUnit\Framework\Constraint\IsEqual;

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
            return;
        }
        
        $editor = $hub->getEditorByVersion('2019.4.17f1');
        if (! $editor->isInstalled()) {
            $this->assertTrue($editor->install(), 'Failed to install Unity Editor 2019.4.17f1');
        }
        
        $target = ServerEnvironment::getCacheDirectory() . DIRECTORY_SEPARATOR . 'EmptyProject';
        
        FileSystem::removeDir($target, true);
        FileSystem::ensureDirectory($target);
        
        $editor->createEmptyProject($target, false);
        
        $result = $editor->execute(false, '-projectPath', $target, '-quit');
        
        $this->assertThat($result->getExitCode(), new IsEqual(0));
    }
}