<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class VersionExecutable extends ProjectExecutableBase {
    
    /** @var string */
    private string $mode;
    
    /** @var string */
    private string $version;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);
        
        $this->mode = $args->get('mode');
        $this->version = $args->get('version');
    }
    
    protected function validate(): void {
        parent::validate();
        
        if ($this->mode === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'mode'!");
        }
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.Version.' . $this->workspaceName;
    }
    
    protected function getExecutableCall(): string {
        return sprintf('%s("%s")', $this->mode, $this->version);
    }
    
    protected function createResultDocument(): ?DOMDocument {
        switch ($this->mode) {
            case 'set':
                $this->project->setProjectVersion($this->version);
                break;
        }
        
        $document = new DOMDocument('1.0', 'UTF-8');
        $node = $document->createElement('version');
        $node->textContent = $this->project->getProjectVersion();
        $document->appendChild($node);
        return $document;
    }
}

