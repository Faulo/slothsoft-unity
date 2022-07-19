<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromDocumentDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Symfony\Component\Process\Process;
use DOMDocument;
use Throwable;

abstract class ExecutableBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $message;

    /** @var UnityProject */
    protected ?UnityProject $project;

    protected function parseArguments(FarahUrlArguments $args): bool {
        $workspace = $args->get('workspace');

        if (! is_dir($workspace)) {
            $this->message = "Workspace '$workspace' is not a directory!";
            return false;
        }

        $workspace = realpath($workspace);

        $hub = UnityHub::getInstance();

        if (! $hub->isInstalled()) {
            $this->message = "Failed to find Unity Hub!";
            return false;
        }

        $this->project = $hub->findProject($workspace);

        if (! $this->project) {
            $this->message = "Workspace '$workspace' does not contain a Unity project!";
            return false;
        }

        if (! $this->project->ensureEditorIsInstalled()) {
            $this->message = "Editor installation for project '$this->project' failed!";
            return false;
        }

        if (! $this->project->ensureEditorIsLicensed()) {
            $this->message = "Editor for project '$this->project' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->project->getEditorVersion()}'.";
            return false;
        }

        return true;
    }

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $resultBuilder = $this->parseArguments($args) ? $this->createSuccessResult() : $this->createErrorResult();

        return new ExecutableStrategies($resultBuilder);
    }

    protected abstract function createSuccessDocument(): DOMDocument;

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            try {
                return $this->createSuccessDocument();
            } catch (Throwable $e) {
                return $this->createErrorDocument($e->getMessage());
            }
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }

    protected function createErrorResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            return $this->createErrorDocument($this->message);
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'error.xml');
    }

    protected function createResultDocument(Process $process): DOMDocument {
        if ($process->getExitCode() !== 0) {
            return $this->createErrorDocument($process->getErrorOutput());
        }
        $document = new DOMDocument();
        $node = $document->createElement('success');
        $document->appendChild($node);
        return $document;
    }

    protected function createErrorDocument(string $message): DOMDocument {
        $document = new DOMDocument();
        $node = $document->createElement('error');
        $node->textContent = $message;
        $document->appendChild($node);
        return $document;
    }
}

