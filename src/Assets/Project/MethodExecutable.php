<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use DOMDocument;

class MethodExecutable extends ExecutableBase {

    /** @var string */
    private string $method;

    /** @var array */
    private array $arguments;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->method = $args->get('method');
        $this->arguments = $args->get('args');
    }

    protected function validate(): bool {
        if ($this->method === '') {
            $this->message = "Missing parameter 'method'!";
            return false;
        }

        return parent::validate();
    }

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.' . basename($this->workspace);
    }

    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->arguments as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('%s(%s)', $this->method, implode(', ', $args));
    }

    protected function createSuccessDocument(): DOMDocument {
        $result = $this->project->executeMethod($this->method, $this->arguments);
        return $this->createResultDocument($result->getExitCode(), $result->getOutput(), $result->getErrorOutput());
    }
}

