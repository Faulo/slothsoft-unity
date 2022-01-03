<?php
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubLocatorTest extends TestCase {
    public function testFindHubLocation() {
        $locator = new UnityHubLocator();
        $this->assertFileExists($locator->findHubLocation());
    }
}