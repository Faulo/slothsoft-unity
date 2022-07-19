<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use DOMDocument;

class TestsExecutable extends ExecutableBase {

    /** @var string[] */
    private array $modes;

    protected function parseArguments(FarahUrlArguments $args): bool {
        $this->modes = $args->get('modes');

        if (! $this->modes) {
            $this->message = "Parameter 'modes' must not be empty!";
            return false;
        }

        return parent::parseArguments($args);
    }

    protected function createSuccessDocument(): DOMDocument {
        return $this->project->runTests(...$this->modes);
    }
}

