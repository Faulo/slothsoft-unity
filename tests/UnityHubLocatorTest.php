<?php
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubLocatorTest extends TestCase {

    public function testFindHubLocation(): void {
        if (PHP_OS !== 'WINNT') {
            $this->markTestSkipped('Unity API is only available on Windows systems.');
            return;
        }
        $locator = new UnityHubLocator();
        $this->assertFileExists($locator->findHubLocation());
    }
}