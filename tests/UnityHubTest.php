<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityHub::class));
    }

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

    public function testGetEditors(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $editors = $hub->getEditors();
        $this->assertIsArray($editors);
        foreach ($editors as $version => $editor) {
            $this->assertEditorIsValid($editor, $version);
        }
    }

    private function assertEditorIsValid(UnityEditor $editor, string $version) {
        $this->assertInstanceOf(UnityEditor::class, $editor);
        $this->assertTrue($editor->isInstalled);
        $this->assertStringContainsString($version, $editor->executable);
    }

    public function testGetEditorPath(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $path = $hub->getEditorPath();
        $this->assertDirectoryExists($path);
    }

    public function testGetEditorByVersion(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $version = '2021.2.7f1';
        $editor = $hub->getEditorByVersion($version);
        $this->assertEditorIsValid($editor, $version);
    }

    public function testFindProject(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(__DIR__ . DIRECTORY_SEPARATOR . 'ValidProject');
        $this->assertInstanceOf(UnityProject::class, $project);
    }
}