<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityProjectInfoTest extends TestCase {

    public const VALID_ROOT = __DIR__;

    public const VALID_PROJECT = __DIR__ . DIRECTORY_SEPARATOR . 'ValidProject';

    public const VALID_PROJECT_VERSION = '2021.2.7f1';

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityProjectInfo::class));
    }

    /**
     *
     * @dataProvider validPathProvider
     */
    public function testFind(string $path) {
        $info = UnityProjectInfo::find($path);
        $this->assertNotNull($info);
        $this->assertInfoIsValid($info);
    }

    /**
     *
     * @dataProvider invalidPathProvider
     */
    public function testNoFind(string $path) {
        $info = UnityProjectInfo::find($path);
        $this->assertNull($info);
    }

    private function assertInfoIsValid(UnityProjectInfo $info) {
        $this->assertDirectoryExists($info->path);
        $this->assertEquals(realpath(self::VALID_PROJECT), realpath($info->path));
        $this->assertEquals(self::VALID_PROJECT_VERSION, $info->editorVersion);
        $this->assertIsArray($info->settings);
        $this->assertIsArray($info->packages);
    }

    /**
     *
     * @dataProvider validPathProvider
     */
    public function testFindAll(string $path) {
        $infos = iterator_to_array(UnityProjectInfo::findAll($path));
        $this->assertCount(1, $infos);
        foreach ($infos as $info) {
            $this->assertInfoIsValid($info);
        }
    }

    public function validPathProvider(): iterable {
        yield self::VALID_PROJECT => [
            self::VALID_PROJECT
        ];
        yield self::VALID_ROOT => [
            self::VALID_ROOT
        ];
    }

    public function invalidPathProvider(): iterable {
        yield self::VALID_PROJECT . DIRECTORY_SEPARATOR . 'Assets' => [
            self::VALID_PROJECT . DIRECTORY_SEPARATOR . 'Assets'
        ];
        yield self::VALID_ROOT . DIRECTORY_SEPARATOR . 'DoesNotExist' => [
            self::VALID_ROOT . DIRECTORY_SEPARATOR . 'DoesNotExist'
        ];
    }
}

