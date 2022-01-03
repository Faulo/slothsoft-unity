<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubTest extends TestCase {
    public function testUseDaemon() {
        UnityHub::setUseDaemon(true);
        $this->assertEquals(true, UnityHub::getUseDaemon());
        
        UnityHub::setUseDaemon(false);
        $this->assertEquals(false, UnityHub::getUseDaemon());
    }
    
    public function testHubIsInstalled() {
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled, 'Please provide a valid Unity Hub installation.');
        $this->assertFileExists($hub->hubFile);
    }

    /**
     *
     * @depends testHubIsInstalled
     */
    public function testExecute() {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $result = $hub->executeNow([
            'install-path',
            '--get'
        ]);
        $this->assertNotNull($result);
        $this->assertDirectoryExists($result);
    }

    /**
     *
     * @depends testHubIsInstalled
     */
    public function testLoadEditors() {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $hub->loadEditors();
        $this->assertIsArray($hub->editors);
        foreach ($hub->editors as $version => $editor) {
            $this->assertStringContainsString($version, $editor->executable);
        }
    }
}