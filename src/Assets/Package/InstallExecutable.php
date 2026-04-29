<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
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
        $project = $this->package->createEmptyProject($this->workspace);
        
        $this->workspace = $project->getProjectPath();
        
        return null;
    }
}
