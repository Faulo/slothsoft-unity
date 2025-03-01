<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class MethodExecutable extends ProjectExecutableBase {

    /** @var string */
    private string $method;

    /** @var int */
    private int $quitOnExit;

    /** @var array */
    private array $arguments;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->method = $args->get('method');
        $this->quitOnExit = $args->get('quit');
        $this->arguments = $args->get('args');
    }

    protected function validate(): void {
        parent::validate();

        if ($this->method === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'method'!");
        }
    }

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Project.CallMethod.' . $this->workspaceName;
    }

    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->arguments as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('%s(%s)', $this->method, implode(', ', $args));
    }

    protected function requiresEditor(): bool {
        return true;
    }

    protected function createResultDocument(): ?DOMDocument {
        if ($this->quitOnExit) {
            $this->project->executeMethod($this->method, $this->arguments);
        } else {
            $this->project->startMethod($this->method, $this->arguments);
        }

        return null;
    }
}

