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
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        $result = $hub->execute('help');
        $this->assertStringContainsString('editors', $result);
    }

    public function testExecute(): void {
        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $result = $hub->execute('install-path', '--get');

        $this->assertNotEquals('', $result);
        $this->assertDirectoryExists($result);
    }

    public function testGetEditors(): void {
        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
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
        $this->assertTrue($editor->isInstalled());
        $this->assertStringContainsString($version, $editor->executable);
    }

    public function testGetEditorPath(): void {
        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $path = $hub->getEditorPath();
        $this->assertDirectoryExists($path);
    }

    public function testGetEditorByVersion(): void {
        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $editors = $hub->getEditors();
        if (count($editors) === 0) {
            $this->markTestSkipped('Needs at least 1 installed editor to test getEditorByVersion.');
            return;
        }

        $editor = array_shift($editors);
        $version = $editor->version;
        $editor = $hub->getEditorByVersion($version);
        $this->assertEditorIsValid($editor, $version);
    }

    /**
     *
     * @dataProvider validUnityVersions
     */
    public function testCreateEditorInstallation(string $version) {
        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $this->assertIsArray($hub->createEditorInstallation($version));
    }

    public function validUnityVersions(): iterable {
        yield '2019.4.17f1' => [
            '2019.4.17f1'
        ];
        yield '2022.2.0a12' => [
            '2022.2.0a12'
        ];
        yield '2022.2.0b1' => [
            '2022.2.0b1'
        ];
    }

    public function testFindLicenses(): void {
        $licenseFolder = __DIR__ . DIRECTORY_SEPARATOR . 'ValidLicenses';
        $licenseFile = realpath($licenseFolder . DIRECTORY_SEPARATOR . 'Unity_v2022.x.ulf');

        UnityHub::setUseDaemon(false);
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        UnityHub::addLicenseFolder($licenseFolder);

        $this->assertEquals([
            $licenseFile
        ], iterator_to_array($hub->findLicenses('2022.1.4')));
    }
}