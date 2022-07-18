<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromElementDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use DOMDocument;
use DOMElement;

abstract class ProjectBuilderBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $message;

    /** @var UnityProject */
    protected UnityProject $project;

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

    protected abstract function createSuccessResult(): ResultBuilderStrategyInterface;

    protected function createErrorResult(): ResultBuilderStrategyInterface {
        $delegate = function (DOMDocument $document): DOMElement {
            $node = $document->createElement('error');
            $node->textContent = $this->message;
            return $node;
        };

        $writer = new DOMWriterFromElementDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'error.xml');
    }
}

