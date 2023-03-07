<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DocFX;

use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\UnityProjectInfoTest;
use SebastianBergmann\CodeCoverage\Node\Directory;

/**
 * SettingsTest
 *
 * @see Settings
 *
 * @todo auto-generated
 */
class SettingsTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(Settings::class), "Failed to load class 'Slothsoft\Unity\DocFX\Settings'!");
    }

    public function testToStringIsJson(): void {
        $settings = new Settings(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertNotNull(json_decode((string) $settings, true));
    }

    public function testContainsCSProj(): void {
        $settings = new Settings(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertStringContainsString('Project.csproj', (string) $settings);
    }

    public function testExport(): void {
        $settings = new Settings(UnityProjectInfoTest::VALID_PROJECT);

        $target = temp_dir(__CLASS__);

        $settings->export($target);

        $this->assertDirectoryExists($target);
        $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'docfx.json');
        $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'index.md');
        $this->assertFileExists($target . DIRECTORY_SEPARATOR . 'toc.yml');

        $this->assertDirectoryExists($target . DIRECTORY_SEPARATOR . '.config');
        $this->assertFileExists($target . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'dotnet-tools.json');
    }
}