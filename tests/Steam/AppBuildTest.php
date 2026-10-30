<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Steam;

use PHPUnit\Framework\TestCase;

/**
 * AppBuildTest
 *
 * @see AppBuild
 *
 */
final class AppBuildTest extends TestCase {
    
    /**
     *
     * @test
     */
    public function testClassExists(): void {
        $this->assertTrue(class_exists(AppBuild::class), "Failed to load class 'Slothsoft\Unity\Steam\AppBuild'!");
    }
    
    public function testConvertsBuildValuesToUtf8(): void {
        $build = new AppBuild('123', "Caf\xe9", 'content', 'build');
        $build->addDepot('456', "Caf\xe9/*");
        $build->setLive("Caf\xe9");
        
        $actual = (string) $build;
        
        $this->assertStringContainsString('Café', $actual);
        $this->assertStringNotContainsString("Caf\xe9", $actual);
        $this->assertSame(3, substr_count($actual, 'Café'));
    }
}
