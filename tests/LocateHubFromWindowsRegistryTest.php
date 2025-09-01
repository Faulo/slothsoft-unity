<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * LocateHubFromWindowsRegistryTest
 *
 * @see LocateHubFromWindowsRegistry
 */
class LocateHubFromWindowsRegistryTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(LocateHubFromWindowsRegistry::class), "Failed to load class 'Slothsoft\Unity\LocateHubFromWindowsRegistry'!");
    }

    public function testFindHubLocation(): void {
        if (PHP_OS !== 'WINNT') {
            $this->markTestSkipped('Unity API is only available on Windows systems.');
            return;
        }
        $locator = new LocateHubFromWindowsRegistry([]);
        $this->assertTrue($locator->exists());
    }
}