<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use DOMXPath;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Slothsoft\Unity\Command\Reporting\JUnitReporter;
use Slothsoft\Unity\Command\Reporting\OperationMetadata;
use SplFileInfo;

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
            $this->assertInstanceof(SplFileInfo::class, $asset);
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

    /**
     * @runInSeparateProcess
     */
    public function testRunTestsTrustsValidReportsRegardlessOfProcessExitCode(): void {
        $project = $this->createSyntheticTestProject([
            'EditMode' => [
                'report' => '<test-run testcasecount="1" total="1" passed="1" failed="0" inconclusive="0" skipped="0" asserts="0"><test-suite name="Edit" classname="Example.Edit"><test-case name="Passes" classname="Example.Edit" result="Passed" /></test-suite></test-run>',
                'exitCode' => 0
            ],
            'PlayMode' => [
                'report' => '<test-run testcasecount="1" total="1" passed="0" failed="0" inconclusive="1" skipped="0" asserts="0"><test-suite name="Play" classname="Example.Play"><test-case name="Inconclusive" classname="Example.Play" result="Inconclusive" /></test-suite></test-run>',
                'exitCode' => 2
            ],
            'EmptyMode' => [
                'report' => '<test-run testcasecount="0" total="0" passed="0" failed="0" inconclusive="0" skipped="0" asserts="0" />',
                'exitCode' => 2
            ]
        ]);

        $report = $project->runTests('EditMode', 'PlayMode', 'EmptyMode');
        $xpath = new DOMXPath($report);

        $this->assertSame('2', $xpath->evaluate('string(/test-run/@testcasecount)'));
        $this->assertSame(2, $xpath->query('//test-case')->length);
        $this->assertSame(0, $xpath->query('//test-case[@label="UnityProcessError"]')->length);
        $this->assertFalse($report->documentElement->hasAttribute('unity-exit-code'));
        $this->assertSame(2, $xpath->query('/test-run/warning')->length);
        $this->assertStringContainsString('PlayMode', $xpath->evaluate('string(/test-run/warning[1])'));
        $this->assertStringContainsString('exit code 2', $xpath->evaluate('string(/test-run/warning[1])'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testRunTestsAddsOneInfrastructureErrorPerUnusablePlatformReport(): void {
        $project = $this->createSyntheticTestProject([
            'EditMode' => [
                'report' => '<test-run start-time="2026-08-16T10:00:00Z" testcasecount="1" total="1" passed="1" failed="0" inconclusive="0" skipped="0" asserts="0"><test-suite name="Edit" classname="Example.Edit" start-time="2026-08-16T10:00:00Z"><test-case name="Passes" classname="Example.Edit" result="Passed" /></test-suite></test-run>'
            ],
            'MissingMode' => [
                'exitCode' => 17
            ],
            'MalformedMode' => [
                'report' => '<test-run>',
                'exitCode' => 2
            ],
            'WrongRootMode' => [
                'report' => '<result />'
            ]
        ]);

        $report = $project->runTests('EditMode', 'MissingMode', 'MalformedMode', 'WrongRootMode');
        $xpath = new DOMXPath($report);

        $this->assertSame('4', $xpath->evaluate('string(/test-run/@testcasecount)'));
        $this->assertSame(1, $xpath->query('//test-case[@name="Passes"]')->length);
        $this->assertSame(3, $xpath->query('//test-case[@label="UnityProcessError"]')->length);
        $this->assertSame([
            'MissingMode',
            'MalformedMode',
            'WrongRootMode'
        ], array_map(static fn ($node): string => $node->getAttribute('value'), iterator_to_array($xpath->query('//test-suite[test-case[@label="UnityProcessError"]]/properties/property[@name="unity-test-platform"]'))));
        $this->assertSame('17', $xpath->evaluate('string(//test-suite[properties/property[@value="MissingMode"]]/properties/property[@name="unity-process.exit-code"]/@value)'));

        $junit = (new JUnitReporter())->createReport($report, new OperationMetadata('tests'));
        $junitXPath = new DOMXPath($junit);
        $this->assertSame(4, $junitXPath->query('//testcase')->length);
        $this->assertSame(3, $junitXPath->query('//testcase/error[@type="UnityProcessError"]')->length);
        $this->assertSame(0, $junitXPath->query('//testcase[@name="Passes"]/*[self::failure or self::error]')->length);
    }

    private function createSyntheticTestProject(array $scenario): UnityProject {
        $encodedScenario = json_encode($scenario, JSON_THROW_ON_ERROR);
        putenv('SLOTHSOFT_UNITY_TEST_SCENARIO=' . $encodedScenario);
        $_ENV['SLOTHSOFT_UNITY_TEST_SCENARIO'] = $encodedScenario;
        $_SERVER['SLOTHSOFT_UNITY_TEST_SCENARIO'] = $encodedScenario;
        $config = UnityHub::getConfig();
        $config->propagateProcessExitCodes = true;
        UnityHub::setConfig($config);

        $info = UnityProjectInfo::find(UnityProjectInfoTest::VALID_PROJECT);
        $this->assertNotNull($info);
        $hub = UnityHub::getInstance();
        $project = new UnityProject($info, $hub);
        $editor = new UnityEditor($hub, $info->editorVersion);
        $runner = PHP_OS_FAMILY === 'Windows' ? 'unity-test-runner.bat' : 'unity-test-runner';
        $editor->setExecutable(__DIR__ . '/../test-files/Command/' . $runner);
        $property = new ReflectionProperty($project, 'editor');
        $property->setValue($project, $editor);

        return $project;
    }
}
