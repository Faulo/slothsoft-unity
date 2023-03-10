<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DocFX;

use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\UnityProjectInfoTest;
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
        $data = (string) $settings;

        $this->assertStringContainsString('Project.csproj', $data);
        $this->assertStringContainsString('Package.csproj', $data);
        $this->assertStringNotContainsString('NotInProject.csproj', $data);
    }

    /**
     *
     * @dataProvider validDocumentations
     */
    public function testExport(string $project, string $documentation): void {
        $settings = new Settings($project);

        $target = temp_dir(__CLASS__);

        $settings->export($target);

        $files = [];
        $files[] = DIRECTORY_SEPARATOR . Settings::FILE_DOCFX;
        $files[] = DIRECTORY_SEPARATOR . Settings::FILE_INDEX;
        $files[] = DIRECTORY_SEPARATOR . Settings::FILE_CHANGELOG;
        $files[] = DIRECTORY_SEPARATOR . Settings::FILE_LICENSE;
        $files[] = DIRECTORY_SEPARATOR . Settings::FILE_TOC;
        $files[] = DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'dotnet-tools.json';

        foreach ($files as $file) {
            if (! file_exists($documentation . $file)) {
                continue;
            }

            $this->assertFileExists($target . $file);

            switch (pathinfo($file, PATHINFO_EXTENSION)) {
                case 'json':
                    $this->assertJsonFileEqualsJsonFile($documentation . $file, $target . $file);
                    break;
                case 'yml':
                    $this->assertEquals(Spyc::YAMLLoad($documentation . $file), Spyc::YAMLLoad($target . $file));
                    break;
                default:
                    $this->assertFileEquals($documentation . $file, $target . $file);
                    break;
            }
        }
    }

    public function testExportDirectory(): void {
        $settings = new Settings(UnityProjectInfoTest::VALID_PROJECT);

        $target = temp_dir(__CLASS__);

        $actual = $settings->export($target);

        $this->assertEquals(realpath($target), $actual);
    }

    public function validDocumentations(): iterable {
        return [
            UnityProjectInfoTest::VALID_PROJECT => [
                UnityProjectInfoTest::VALID_PROJECT,
                UnityProjectInfoTest::VALID_DOCUMENTATION
            ],
            UnityProjectInfoTest::VALID_PROJECT_WITH_MDS => [
                UnityProjectInfoTest::VALID_PROJECT_WITH_MDS,
                UnityProjectInfoTest::VALID_DOCUMENTATION_WITH_MDS
            ]
        ];
    }
}