<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use PHPUnit\Framework\TestCase;

/**
 * AppBuildTest
 *
 * @see AppBuild
 *
 * @todo auto-generated
 */
class AppBuildTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(AppBuild::class), "Failed to load class 'Slothsoft\Unity\Steam\AppBuild'!");
    }

    /**
     *
     * @dataProvider validEncodings
     */
    public function testToStringIsUTF8(string $encoding): void {
        $appId = '123';
        $description = 'Hellö';
        $contentPath = '/var/server/öäü';
        $buildPath = 'C:\\Webserver\\workspace@tmp';
        $branch = 'mäin';

        $appId2 = mb_convert_encoding($appId, $encoding, 'UTF-8');
        $description2 = mb_convert_encoding($description, $encoding, 'UTF-8');
        $contentPath2 = mb_convert_encoding($contentPath, $encoding, 'UTF-8');
        $buildPath2 = mb_convert_encoding($buildPath, $encoding, 'UTF-8');
        $branch2 = mb_convert_encoding($branch, $encoding, 'UTF-8');

        $app = new AppBuild($appId2, $description2, $contentPath2, $buildPath2);
        $app->setLive($branch2);

        $content = (string) $app;
        $this->assertEquals('UTF-8', mb_detect_encoding($content));
        $this->assertStringContainsString($appId, $content);
        $this->assertStringContainsString($description, $content);
        $this->assertStringContainsString($contentPath, $content);
        $this->assertStringContainsString($buildPath, $content);
        $this->assertStringContainsString($branch, $content);
    }

    public function validEncodings(): iterable {
        return [
            [
                'UTF-8'
            ],
            [
                'Windows-1252'
            ],
            [
                'ISO-8859-1'
            ]
        ];
    }
}