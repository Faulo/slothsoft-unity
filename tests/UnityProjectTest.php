<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;

class UnityProjectTest extends TestCase {

    public function testClassExists() {
        $this->assertTrue(class_exists(UnityProject::class));
    }

    public function testFindProject(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);
        $this->assertNotNull($project);
        $this->assertEquals(UnityProjectInfoTest::VALID_PROJECT, $project->getProjectPath());
    }

    public function testNoFindProject(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_ROOT . DIRECTORY_SEPARATOR . 'MissingDirectory');
        $this->assertNull($project);
    }

    public function testGetAssetFiles(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $assets = iterator_to_array($project->getAssetFiles());

        $files = [];
        $files[] = 'NotInProject.asmdef';
        $files[] = 'Project.asmdef';
        $files[] = 'Script.cs';

        $this->assertCount(count($files), $assets);

        foreach ($assets as $asset) {
            $this->assertInstanceof(\SplFileInfo::class, $asset);
            $this->assertContains($asset->getBasename(), $files);
        }
    }

    public function testSettingSuccess(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertTrue($project->hasSetting('companyName'));
        $this->assertEquals('Oilcatz', $project->getSetting('companyName'));
    }

    public function testSettingFailure(): void {
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            $this->markTestSkipped('Please provide a valid Unity Hub installation.');
            return;
        }

        $project = $hub->findProject(UnityProjectInfoTest::VALID_PROJECT);

        $this->assertFalse($project->hasSetting('???'));
        $this->assertEquals('Oilcatz', $project->getSetting('???', 'Oilcatz'));
    }
}