<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityHubTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityHub::class));
    }

    public function testLoggingEnabled() {
        foreach ([
            true,
            false
        ] as $value) {
            UnityHub::setLoggingEnabled($value);
            $this->assertEquals($value, UnityHub::getLoggingEnabled());
        }
    }

    public function testThrowOnFailure() {
        foreach ([
            true,
            false
        ] as $value) {
            UnityHub::setThrowOnFailure($value);
            $this->assertEquals($value, UnityHub::getThrowOnFailure());
        }
    }

    public function testProcessTimeout() {
        foreach ([
            0,
            60
        ] as $value) {
            UnityHub::setProcessTimeout($value);
            $this->assertEquals($value, UnityHub::getProcessTimeout());
        }
    }

    public function testHubIsInstalled(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }
        $result = $hub->execute('help');
        $errors = trim($result->getErrorOutput());
        $ouput = trim($result->getOutput());

        $this->assertEquals('', $errors);
        $this->assertNotEquals('', $ouput);
        $this->assertStringContainsString('editors', $ouput);
    }

    public function testExecute(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $result = $hub->execute('install-path', '--get');
        $errors = trim($result->getErrorOutput());
        $ouput = trim($result->getOutput());

        $this->assertEquals('', $errors);
        $this->assertNotEquals('', $ouput);
        $this->assertDirectoryExists($ouput);
    }

    public function testGetEditors(): void {
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
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $path = $hub->getEditorPath();
        $this->assertDirectoryExists($path);
    }

    public function testGetEditorByVersion(): void {
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
        yield '6000.0.40f1' => [
            '6000.0.40f1'
        ];
    }

    const VALID_LICENSE_DIRECTORY = __DIR__ . '/../test-files/ValidLicenses';

    public function testFindLicenses(): void {
        $licenseFolder = self::VALID_LICENSE_DIRECTORY;
        $licenseFile = realpath(self::VALID_LICENSE_DIRECTORY . DIRECTORY_SEPARATOR . 'Unity_v2022.x.ulf');

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

    const VALID_PACKAGE_DIRECTORY = __DIR__ . '/../test-files/ValidPackage';

    public function testFindPackage(): void {
        $packageFolder = self::VALID_PACKAGE_DIRECTORY;

        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $package = $hub->findPackage($packageFolder);

        $this->assertNotNull($package, "Failed to find package!");
    }

    /**
     *
     * @dataProvider editorVersions
     */
    public function testInventStableEditorVersion(string $minVersion, $expectedVersion) {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $actualVersion = $hub->inventStableEditorVersion($minVersion);

        $this->assertEquals($expectedVersion, $actualVersion);
    }

    public function editorVersions(): iterable {
        yield '2020.1' => [
            '2020.1',
            '2020.1.0f1'
        ];
        yield '2019.3' => [
            '2019.3',
            '2019.3.0f1'
        ];
        yield '2019.4' => [
            '2019.4',
            '2019.4.0f1'
        ];
        yield '2019.4.40f1' => [
            '2019.4.40f1',
            '2019.4.40f1'
        ];
        yield '6000.0.0f1' => [
            '6000',
            '6000.0.0f1'
        ];
        yield '6000.1.0f1' => [
            '6000.1',
            '6000.1.0f1'
        ];
        yield '6000.0.40f1' => [
            '6000.0.40f1',
            '6000.0.40f1'
        ];
    }
}