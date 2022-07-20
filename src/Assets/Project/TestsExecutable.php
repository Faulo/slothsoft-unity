<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use DOMDocument;

class TestsExecutable extends ExecutableBase {

    /** @var string[] */
    private array $modes;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->modes = $args->get('modes');
    }

    protected function validate(): ?ExecutionError {
        if (! $this->modes) {
            return ExecutionError::Error('AssertParameter', "Parameter 'modes' must not be empty!");
        }

        return parent::validate();
    }

    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->modes as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('RunTests(%s)', implode(', ', $args));
    }

    protected function createSuccessDocument(): DOMDocument {
        return $this->project->runTests(...$this->modes);
    }
}

