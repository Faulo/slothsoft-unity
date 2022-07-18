<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromDocumentDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
use DOMDocument;

class ProjectBuildBuilder extends ProjectBuilderBase {

    /** @var string */
    private string $target;

    /** @var string */
    private string $path;

    protected function parseArguments(FarahUrlArguments $args): bool {
        $this->target = $args->get('target');
        $this->path = $args->get('path');

        if ($this->target === '') {
            $this->message = "Missing parameter 'target'!";
            return false;
        }

        if ($this->path === '') {
            $this->message = "Missing parameter 'path'!";
            return false;
        }

        return parent::parseArguments($args);
    }

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            return $this->project->build($this->target, $this->path);
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }
}

