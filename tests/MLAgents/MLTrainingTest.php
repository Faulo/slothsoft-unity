<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\MLAgents;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Slothsoft\Unity\UnityProjectInfo;

/**
 * MLTrainingTest
 *
 * @see MLTraining
 *
 */
final class MLTrainingTest extends TestCase {
    
    /**
     *
     * @test
     */
    public function testClassExists(): void {
        $this->assertTrue(class_exists(MLTraining::class), "Failed to load class 'Slothsoft\Unity\MLAgents\MLTraining'!");
    }
    
    /** @runInSeparateProcess */
    public function testTrainingDiscoveryRejectsMissingPackageVersion(): void {
        $info = UnityProjectInfo::find(dirname(__DIR__, 2) . '/test-files/ValidProject');
        $this->assertNotNull($info);
        $info->packages['com.unity.ml-agents'] = ['source' => 'registry'];
        $project = new UnityProject($info, UnityHub::getInstance());
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have a valid ML-Agents package version');
        iterator_to_array(MLTraining::determineTrainings($project, '', '', ''));
    }
    
    public function testTrainRequiresInitialization(): void {
        $directory = temp_dir('ml-training-init');
        $training = new MLTraining(new MLContext($directory, '0.23'), 'test', __FILE__, $directory);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Call init()');
        $training->train($directory, __FILE__);
    }
}
