<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class TestsExecutable extends ProjectExecutableBase {
    
    /** @var string[] */
    private array $modes;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);
        
        $this->modes = $args->get('modes');
    }
    
    protected function validate(): void {
        parent::validate();
        
        if (! $this->modes) {
            throw ExecutionError::Error('AssertParameter', "Parameter 'modes' must not be empty!");
        }
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.RunTests.' . $this->workspaceName;
    }
    
    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->modes as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('RunTests(%s)', implode(', ', $args));
    }
    
    protected function requiresEditor(): bool {
        return true;
    }
    
    protected function createResultDocument(): ?DOMDocument {
        return $this->project->runTests(...$this->modes);
    }
}

