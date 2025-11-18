<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * UnityPackageTest
 *
 * @see UnityPackage
 */
final class UnityPackageTest extends TestCase {
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityPackage::class), "Failed to load class 'Slothsoft\Unity\UnityPackage'!");
    }
    
    public function test_emptyManifestFile(): void {
        $this->assertFileExists(UnityPackage::getEmptyManifestFile());
    }
}