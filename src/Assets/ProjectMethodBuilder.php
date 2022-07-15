<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromElementDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use DOMDocument;
use DOMElement;

class ProjectMethodBuilder implements ExecutableBuilderStrategyInterface {

    /** @var string */
    private string $message;

    /** @var UnityProject */
    private UnityProject $project;

    /** @var array */
    private array $arguments;

    private function parseArguments(FarahUrlArguments $args): bool {
        $workspace = $args->get('workspace');
        $this->arguments = $args->get('args');

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
            $this->message = "Editor for project '$this->project' is not licensed!";
            return false;
        }

        return true;
    }

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        if ($this->parseArguments($args)) {
            $delegate = function (DOMDocument $document): DOMElement {
                $node = $document->createElement('result');
                $node->textContent = $this->project->executeMethod(...$this->arguments);
                return $node;
            };
        } else {
            $delegate = function (DOMDocument $document): DOMElement {
                $node = $document->createElement('error');
                $node->textContent = $this->message;
                return $node;
            };
        }

        $writer = new DOMWriterFromElementDelegate($delegate);

        $resultBuilder = new DOMWriterResultBuilder($writer, 'result.xml');

        return new ExecutableStrategies($resultBuilder);
    }
}

