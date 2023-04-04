<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * UnityPackageInfoTest
 *
 * @see UnityPackageInfo
 *
 * @todo auto-generated
 */
class UnityPackageInfoTest extends TestCase {

    public const VALID_ROOT = __DIR__ . '/../test-files';

    public const VALID_PACKAGE = __DIR__ . '/../test-files/ValidPackage';

    public const VALID_PACKAGE_WITH_PATCH = __DIR__ . '/../test-files/ValidPackageWithPatch';

    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityPackageInfo::class), "Failed to load class 'Slothsoft\Unity\UnityPackageInfo'!");
    }

    /**
     *
     * @dataProvider validPackageProvider
     */
    public function testFind(string $path, string $minUnityVersion) {
        $info = UnityPackageInfo::find($path);
        $this->assertNotNull($info);
    }

    /**
     *
     * @dataProvider validPackageProvider
     */
    public function testMinEditorVersion(string $path, string $minEditorVersion) {
        $info = UnityPackageInfo::find($path);
        $this->assertEquals($minEditorVersion, $info->getMinEditorVersion());
    }

    public function validPackageProvider(): iterable {
        yield self::VALID_PACKAGE => [
            self::VALID_PACKAGE,
            '2022.1'
        ];
        yield self::VALID_PACKAGE_WITH_PATCH => [
            self::VALID_PACKAGE_WITH_PATCH,
            '2019.1.0b5'
        ];
    }
}