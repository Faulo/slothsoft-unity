<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

/**
 * Creates a temporary Unity project with a local package installed.
 *
 * @author Daniel Schulz
 * @since 2022-08-13
 */
final class InstallExecutable extends PackageExecutableBase {
    
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
        $workspace = realpath($this->workspace);
        if ($workspace === false) {
            throw ExecutionError::Error('AssertDirectory', "Failed to resolve workspace '{$this->workspace}'!");
        }
        $this->workspace = $workspace;
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Package.' . $this->packageName;
    }
    
    protected function getExecutableCall(): string {
        return sprintf('CreateEmptyProject("%s")', $this->workspace);
    }
    
    protected function createResultDocument(): ?DOMDocument {
        $package = $this->package ?? throw ExecutionError::Error('AssertPackage', "Missing Unity package at '{$this->packageDirectory}'!");
        $project = $package->createEmptyProject($this->workspace);
        
        $this->workspace = $project->getProjectPath();
        
        return null;
    }
}
