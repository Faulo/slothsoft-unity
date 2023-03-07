<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DocFX;

use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\UnityProjectInfoTest;
use SebastianBergmann\CodeCoverage\Node\Directory;
use Spyc;

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

        $files = [];
        $files[] = DIRECTORY_SEPARATOR . 'docfx.json';
        $files[] = DIRECTORY_SEPARATOR . 'index.md';
        $files[] = DIRECTORY_SEPARATOR . 'toc.yml';
        $files[] = DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'dotnet-tools.json';

        foreach ($files as $file) {
            $this->assertFileExists($target . $file);

            switch (pathinfo($file, PATHINFO_EXTENSION)) {
                case 'json':
                    $this->assertJsonFileEqualsJsonFile(UnityProjectInfoTest::VALID_DOCUMENTATION . $file, $target . $file);
                    break;
                case 'yml':
                    $this->assertEquals(Spyc::YAMLLoad(UnityProjectInfoTest::VALID_DOCUMENTATION . $file), Spyc::YAMLLoad($target . $file));
                    break;
                default:
                    $this->assertFileEquals(UnityProjectInfoTest::VALID_DOCUMENTATION . $file, $target . $file);
                    break;
            }
        }
    }
}