<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class InstallExecutable extends PackageExecutableBase {
    
    /** @var string */
    private string $workspace;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);
        
        $this->workspace = $args->get('workspace');
    }
    
    protected function validate(): void {
        parent::validate();
        
        if (! is_dir($this->workspace)) {
            mkdir($this->workspace, 0777, true);
        }
        $this->workspace = realpath($this->workspace);
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Package.' . $this->packageName;
    }
    
    protected function getExecutableCall(): string {
        return sprintf('CreateEmptyProject("%s")', $this->workspace);
    }
    
    protected function createResultDocument(): ?DOMDocument {
        if (! $this->package->ensureEditorIsLicensed($this->workspace)) {
            throw ExecutionError::Error('AssertLicense', "Editor for package '{$this->package}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->package->getEditorVersion()}'.");
        }
        
        $this->project = $this->package->createEmptyProject($this->workspace);
        
        $this->workspace = $this->project->getProjectPath();
        
        return null;
    }
}
