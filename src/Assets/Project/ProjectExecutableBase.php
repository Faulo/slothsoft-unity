<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Slothsoft\Unity\Assets\ExecutableBase;

/**
 * Base strategy for Farah executables that operate on Unity projects.
 *
 * @author Daniel Schulz
 * @since 2022-08-15
 */
abstract class ProjectExecutableBase extends ExecutableBase implements ExecutableBuilderStrategyInterface {
    
    protected string $workspace;
    
    protected string $workspaceName = 'Unknown';
    
    protected ?UnityProject $project;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        $this->workspace = $args->get('workspace');
        if ($workspace = realpath($this->workspace)) {
            $name = basename($workspace);
            $this->workspaceName = preg_replace('~\s+~', '', $name) ?? $name;
        }
    }
    
    protected function validate(): void {
        $workspace = realpath($this->workspace);
        if ($workspace === false or ! is_dir($workspace)) {
            throw ExecutionError::Error('AssertDirectory', "Workspace '{$this->workspace}' is not a directory!");
        }
        
        $this->workspace = $workspace;
        
        $hub = UnityHub::getInstance();
        
        if (! $hub->isInstalled()) {
            throw ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
        }
        
        $this->project = $hub->findProject($this->workspace);
        
        if (! $this->project) {
            throw ExecutionError::Error('AssertProject', "Workspace '{$this->workspace}' does not contain a Unity project!");
        }
        
        if ($this->requiresEditor()) {
            if (! $this->project->ensureEditorIsInstalled()) {
                throw ExecutionError::Error('AssertEditor', "Editor installation for project '{$this->project}' failed!");
            }
            
            if (! $this->project->ensureEditorIsLicensed()) {
                throw ExecutionError::Error('AssertLicense', "Editor for project '{$this->project}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->project->getEditorVersion()}'.");
            }
        }
    }
    
    protected function requiresEditor(): bool {
        return false;
    }

    final protected function requireProject(): UnityProject {
        return $this->project ?? throw ExecutionError::Error('AssertProject', "Workspace '{$this->workspace}' does not contain a Unity project!");
    }
}
