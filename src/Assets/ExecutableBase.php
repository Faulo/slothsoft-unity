<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromDocumentDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Unity\ExecutionError;
use DOMDocument;
use Throwable;

abstract class ExecutableBase implements ExecutableBuilderStrategyInterface {

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $this->parseArguments($args);

        $delegate = function (): DOMDocument {
            $result = new TestResult($this->getExecutablePackage(), $this->getExecutableCall());

            try {
                $this->validate();

                $document = $this->createResultDocument();
                if ($document) {
                    return $document;
                }
            } catch (ExecutionError $e) {
                $result->setError($e);
            } catch (Throwable $e) {
                $result->setError(ExecutionError::Exception($e));
            }

            $document = new DOMDocument();

            $document->appendChild($result->asNode($document));

            return $document;
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        $resultBuilder = new DOMWriterResultBuilder($writer, 'result.xml');

        return new ExecutableStrategies($resultBuilder);
    }

    protected abstract function parseArguments(FarahUrlArguments $args): void;

    protected abstract function validate(): void;

    protected abstract function createResultDocument(): ?DOMDocument;

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration';
    }

    protected function getExecutableCall(): string {
        return get_class($this);
    }
}

