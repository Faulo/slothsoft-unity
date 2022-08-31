<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class BuildExecutable extends ProjectExecutableBase {

    /** @var string */
    private string $target;

    /** @var string */
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

    protected function createResultDocument(): ?DOMDocument {
        $this->project->build($this->target, $this->path);
        return null;
    }
}

