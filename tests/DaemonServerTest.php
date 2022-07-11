<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

/**
 * DaemonServerTest
 *
 * @see DaemonServer
 *
 * @todo auto-generated
 */
class DaemonServerTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(DaemonServer::class), "Failed to load class 'Slothsoft\Unity\DaemonServer'!");
    }
}