<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

/**
 * Runs a Unity player build for a project asset request.
 *
 * @author Daniel Schulz
 * @since 2022-07-11
 */
final class BuildExecutable extends ProjectExecutableBase {
    
    private string $target;
    
    private string $path;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);
        
        $this->target = $args->get('target');
        $this->path = $args->get('path');
    }
    
    protected function validate(): void {
        parent::validate();
        
        if ($this->target === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'target'!");
        }
        
        if ($this->path === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'path'!");
        }
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.Build.' . $this->workspaceName;
    }
    
    protected function getExecutableCall(): string {
        return sprintf('Build("%s")', $this->target);
    }
    
    protected function requiresEditor(): bool {
        return true;
    }
    
    protected function createResultDocument(): ?DOMDocument {
        $this->project->build($this->target, $this->path);
        return null;
    }
}
