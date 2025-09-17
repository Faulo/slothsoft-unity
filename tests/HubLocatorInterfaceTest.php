<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * HubLocatorInterfaceTest
 *
 * @see HubLocatorInterface
 *
 * @todo auto-generated
 */
class HubLocatorInterfaceTest extends TestCase {
    
    public function testInterfaceExists(): void {
        $this->assertTrue(interface_exists(HubLocatorInterface::class), "Failed to load interface 'Slothsoft\Unity\HubLocatorInterface'!");
    }
}