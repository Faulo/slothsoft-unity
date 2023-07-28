<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class SettingExecutable extends ProjectExecutableBase {

    /** @var string */
    private string $name;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->name = $args->get('name');
    }

    protected function validate(): void {
        parent::validate();

        if ($this->name === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'name'!");
        }
    }

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.Setting.' . $this->workspaceName;
    }

    protected function getExecutableCall(): string {
        return sprintf('%s("%s")', 'Setting', $this->name);
    }

    protected function createResultDocument(): ?DOMDocument {
        $document = new DOMDocument('1.0', 'UTF-8');
        $node = $document->createElement($this->name);
        $node->textContent = $this->project->getSetting($this->name, '');
        $document->appendChild($node);
        return $document;
    }
}

