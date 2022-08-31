<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class ModuleExecutable extends ProjectExecutableBase {

    /** @var string[] */
    private array $modules;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);
        $this->modules = $args->get('modules', []);
    }

    protected function validate(): void {
        parent::validate();

        if (! $this->modules) {
            throw ExecutionError::Error('AssertParameter', "Parameter 'modules' must not be empty!");
        }
    }

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.InstallModules.' . $this->workspaceName;
    }

    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->modules as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('InstallModules(%s)', implode(', ', $args));
    }

    protected function createResultDocument(): ?DOMDocument {
        if ($this->project->ensureEditorIsInstalled()) {
            $this->project->installModules(...$this->modules);
        }
        return null;
        ;
    }
}

