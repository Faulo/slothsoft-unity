<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;

class MethodExecutable extends ProjectExecutableBase {

    /** @var string */
    private string $method;

    /** @var array */
    private array $arguments;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->method = $args->get('method');
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

    protected function createResultDocument(): ?DOMDocument {
        $result = $this->project->executeMethod($this->method, $this->arguments);

        if ($result->getExitCode() !== 0) {
            throw ExecutionError::Failure('AssertMethod', "Calling method '{$this->method}' failed!", $result);
        }

        return null;
    }
}

