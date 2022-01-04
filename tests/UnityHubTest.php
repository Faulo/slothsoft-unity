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

    public function testExecute() {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $result = $hub->executeNow([
            'install-path',
            '--get'
        ]);

        $this->assertNotEquals('', $result);
        $this->assertDirectoryExists($result);
    }

    public function testLoadEditors() {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $hub->loadEditors();
        $this->assertIsArray($hub->editors);
        foreach ($hub->editors as $version => $editor) {
            $this->assertTrue($editor->isInstalled);
            $this->assertStringContainsString($version, $editor->executable);
        }
    }

    public function testGetEditorByVersion() {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $this->assertInstanceOf(UnityEditor::class, $hub->getEditorByVersion('2021.2.7f1'));
    }
}