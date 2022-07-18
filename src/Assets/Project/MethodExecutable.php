<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromElementDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
use DOMDocument;
use DOMElement;

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

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (DOMDocument $document): DOMElement {
            $node = $document->createElement('result');
            $node->textContent = $this->project->executeMethod($this->method, $this->arguments);
            return $node;
        };

        $writer = new DOMWriterFromElementDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }
}

