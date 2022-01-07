<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubLocatorTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityHubLocator::class));
    }

    public function testFindHubLocation(): void {
        if (PHP_OS !== 'WINNT') {
            $this->markTestSkipped('Unity API is only available on Windows systems.');
            return;
        }
        $locator = new UnityHubLocator();
        $this->assertFileExists($locator->findHubLocation());
    }
}