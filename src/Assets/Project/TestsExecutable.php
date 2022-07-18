<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromDocumentDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
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

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            return $this->project->runTests(...$this->modes);
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }
}

