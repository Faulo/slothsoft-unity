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
        $this->assertInstanceOf(UnityProject::class, $project);
        $this->assertEquals(UnityProjectInfoTest::VALID_PROJECT, $project->getProjectPath());
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
        $this->assertEquals('Script.cs', $asset->getBaseName());
    }
}