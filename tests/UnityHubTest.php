<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubTest extends TestCase {

    public function testHubExists() {
        $hub = new UnityHub();
        if ($hub->isInstalled) {
            $this->assertFileExists($hub->hubFile);
            $this->assertDirectoryExists($hub->workspaceDirectory);
        } else {
            $this->markTestSkipped('Please provide a valid Unity Hub installation via UnityHub::setHubLocation, setEditorLocation and setWorkspaceLocation');
        }
    }

    /**
     *
     * @depends testHubExists
     */
    public function testExecute() {
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
     * @depends testHubExists
     */
    public function testLoadEditors() {
        $hub = new UnityHub();
        $this->assertTrue($hub->isInstalled);

        $hub->loadEditors();
        $this->assertIsArray($hub->editors);
        foreach ($hub->editors as $version => $editor) {
            $this->assertStringContainsString($version, $editor->executable);
        }
    }
}