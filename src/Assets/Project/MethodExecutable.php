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

    protected function parseArguments(FarahUrlArguments $args): bool {
        $this->method = $args->get('method');
        $this->arguments = $args->get('args');

        if ($this->method === '') {
            $this->message = "Missing parameter 'method'!";
            return false;
        }

        return parent::parseArguments($args);
    }

    protected function createSuccessDocument(): DOMDocument {
        $result = $this->project->executeMethod($this->method, $this->arguments);
        return $this->createResultDocument($result);
    }
}

