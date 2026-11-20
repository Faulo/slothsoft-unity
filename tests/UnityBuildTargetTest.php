<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * UnityBuildTargetTest
 *
 * @see UnityBuildTarget
 *
 */
final class UnityBuildTargetTest extends TestCase {
    
    /**
     *
     * @test
     */
    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityBuildTarget::class), "Failed to load class 'Slothsoft\Unity\UnityBuildTarget'!");
    }
    
    public function testBuildMappings(): void {
        $this->assertSame(PHP_OS === 'WINNT' ? [] : ['windows-mono'], UnityBuildTarget::getEditoModules('WINDOWS'));
        $this->assertSame(PHP_OS === 'Linux' ? [] : ['linux-mono'], UnityBuildTarget::getEditoModules('Linux'));
        $this->assertSame(['mac-il2cpp'], UnityBuildTarget::getEditoModules('Mac', UnityBuildTarget::BACKEND_IL2CPP));
        $this->assertSame('Player.exe', UnityBuildTarget::getBuildExecutable('windows', 'Player'));
        $this->assertSame(['-buildTarget', 'Linux64', '-buildLinux64Player', 'Player'], UnityBuildTarget::getBuildParameters('linux', 'Player'));
    }
    
    public function testUnsupportedScriptingBackendIsRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        UnityBuildTarget::getEditoModules('windows', 2);
    }
}
