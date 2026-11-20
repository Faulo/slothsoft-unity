<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\DocFX;

use PHPUnit\Framework\TestCase;

/**
 * SettingsTest
 *
 * @see Settings
 *
 */
final class SettingsTest extends TestCase {
    
    /**
     *
     * @test
     */
    public function testClassExists(): void {
        $this->assertTrue(class_exists(Settings::class), "Failed to load class 'Slothsoft\Unity\DocFX\Settings'!");
    }
    
    public function testAssetScanToleratesDanglingSymlink(): void {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Creating symlinks on Windows requires a configured host.');
        }
        
        $workspace = temp_dir('docfx-dangling-link');
        $this->assertTrue(mkdir($workspace . '/Assets/Plugins', 0777, true));
        $this->assertTrue(mkdir($workspace . '/Packages'));
        $this->assertTrue(symlink($workspace . '/missing', $workspace . '/Assets/missing'));
        try {
            $this->assertTrue(symlink($workspace . '/not-here', $workspace . '/missing'));
            try {
                $settings = new Settings($workspace);
                $this->assertStringContainsString('"metadata"', (string) $settings);
            } finally {
                unlink($workspace . '/missing');
            }
        } finally {
            unlink($workspace . '/Assets/missing');
        }
    }
}
