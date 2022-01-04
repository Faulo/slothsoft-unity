<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubTest extends TestCase {

    public function testUseDaemon(): void {
        UnityHub::setUseDaemon(true);
        $this->assertEquals(true, UnityHub::getUseDaemon());

        UnityHub::setUseDaemon(false);
        $this->assertEquals(false, UnityHub::getUseDaemon());
    }

    public function testHubIsInstalled(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        $this->assertFileExists($hub->hubFile);
    }

    public function testExecute(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $result = $hub->executeNow([
            'install-path',
            '--get'
        ]);

        $this->assertNotEquals('', $result);
        $this->assertDirectoryExists($result);
    }

    public function testLoadEditors(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $hub->loadEditors();
        $this->assertIsArray($hub->editors);
        foreach ($hub->editors as $version => $editor) {
            $this->assertTrue($editor->isInstalled);
            $this->assertStringContainsString($version, $editor->executable);
        }
    }

    public function testGetEditorByVersion(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $this->assertInstanceOf(UnityEditor::class, $hub->getEditorByVersion('2021.2.7f1'));
    }
}