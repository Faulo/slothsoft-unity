<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class LocateHubForWindowsTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(LocateHubForWindows::class));
    }

    public function testFindHubLocation(): void {
        if (PHP_OS !== 'WINNT') {
            $this->markTestSkipped('Unity API is only available on Windows systems.');
            return;
        }
        $locator = new LocateHubForWindows();
        $this->assertFileExists($locator->locate());
        $this->assertTrue($locator->exists());
    }
}