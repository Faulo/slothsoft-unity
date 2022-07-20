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

    protected function validate(): void {
        if ($this->method === '') {
            $this->error = ExecutionError::Error('AssertParameter', "Missing parameter 'method'!");
            return;
        }

        parent::validate();
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
        $code = $result->getExitCode();
        $stdout = $result->getOutput();
        $stderr = $result->getErrorOutput();

        if ($code !== 0) {
            $this->error = ExecutionError::Failure('AssertMethod', "Calling method '{$this->method}' failed!");
        }

        return $this->createResultDocument($code, $stdout, $stderr, $this->error);
    }
}

