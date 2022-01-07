<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityProjectTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityProject::class));
    }

    public function testFindProject(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);
        $this->assertNotNull($project);
        $this->assertEquals(UnityProjectInfoTest::VALID_PROJECT, $project->getProjectPath());
    }

    public function testNoFindProject(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_ROOT . DIRECTORY_SEPARATOR . 'MissingDirectory');
        $this->assertNull($project);
    }

    public function testGetAssetFiles(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $assets = iterator_to_array($project->getAssetFiles());

        $this->assertCount(1, $assets);

        $asset = $assets[0];
        $this->assertInstanceof(\SplFileInfo::class, $asset);
        $this->assertEquals('Script.cs', $asset->getBasename());
    }

    public function testSettingSuccess(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertTrue($project->hasSetting('companyName'));
        $this->assertEquals('Oilcatz', $project->getSetting('companyName'));
    }

    public function testSettingFailure(): void {
        UnityHub::setUseDaemon(false);
        $hub = new UnityHub();
        if (! $hub->isInstalled) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertFalse($project->hasSetting('???'));
        $this->assertEquals('Oilcatz', $project->getSetting('???', 'Oilcatz'));
    }
}