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

    protected function validate(): bool {
        if (! $this->modes) {
            $this->message = "Parameter 'modes' must not be empty!";
            return false;
        }

        return parent::validate();
    }

    protected function getExecutablePackage(): string {
        return 'Slothsoft.Unity.Project.Tests';
    }

    protected function getExecutableCall(): string {
        $args = [];
        foreach ($this->modes as $arg) {
            $args[] = sprintf('"%s"', $arg);
        }
        return sprintf('Test(%s)', implode(', ', $args));
    }

    protected function createSuccessDocument(): DOMDocument {
        return $this->project->runTests(...$this->modes);
    }
}

