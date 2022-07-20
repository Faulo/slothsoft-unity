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
use DOMDocument;
use DateTime;
use Throwable;

abstract class ExecutableBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $workspace;

    /** @var ExecutionError */
    protected ?ExecutionError $error;

    /** @var UnityProject */
    protected ?UnityProject $project;

    /** @var float */
    private float $startTime;

    protected function parseArguments(FarahUrlArguments $args): void {
        $this->workspace = $args->get('workspace');
    }

    protected function validate(): void {
        if (! is_dir($this->workspace)) {
            $this->error = ExecutionError::Error('AssertDirectory', "Workspace '{$this->workspace}' is not a directory!");
            return;
        }

        $this->workspace = realpath($this->workspace);

        $hub = UnityHub::getInstance();

        if (! $hub->isInstalled()) {
            $this->error = ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
            return;
        }

        $this->project = $hub->findProject($this->workspace);

        if (! $this->project) {
            $this->error = ExecutionError::Error('AssertProject', "Workspace '{$this->workspace}' does not contain a Unity project!");
            return;
        }

        if (! $this->project->ensureEditorIsInstalled()) {
            $this->error = ExecutionError::Error('AssertEditor', "Editor installation for project '{$this->project}' failed!");
            return;
        }

        if (! $this->project->ensureEditorIsLicensed()) {
            $this->error = ExecutionError::Error('AssertLicense', "Editor for project '{$this->project}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->project->getEditorVersion()}'.");
            return;
        }

        return;
    }

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $this->startTime = microtime(true);
        $this->error = null;

        $this->parseArguments($args);

        $this->validate();

        $resultBuilder = $this->error ? $this->createErrorResult() : $this->createSuccessResult();

        return new ExecutableStrategies($resultBuilder);
    }

    protected abstract function createSuccessDocument(): DOMDocument;

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.' . preg_replace('~[^a-zA-Z0-9]~', '', basename($this->workspace));
    }

    protected abstract function getExecutableCall(): string;

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            try {
                return $this->createSuccessDocument();
            } catch (Throwable $e) {
                $code = $e->getCode();
                if ($code === 0) {
                    $code = - 1;
                }
                return $this->createResultDocument($code, '', (string) $e, ExecutionError::Error(get_class($e), $e->getMessage()));
            }
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }

    protected function createErrorResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            return $this->createResultDocument(- 1, '', '', $this->error);
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'error.xml');
    }

    protected function createResultDocument(int $code, string $stdout, string $stderr, ?ExecutionError $error): DOMDocument {
        $document = new DOMDocument();
        $rootNode = $document->createElement('result');

        $node = $document->createElement('process');
        $node->setAttribute('package', $this->getExecutablePackage());
        $node->setAttribute('name', $this->getExecutableCall());
        $node->setAttribute('result', (string) $code);
        $node->setAttribute('stdout', $stdout);
        $node->setAttribute('stderr', $stderr);
        $node->setAttribute('start-time', date(DateTime::W3C, (int) $this->startTime));
        $node->setAttribute('duration', sprintf('%0.06f', microtime(true) - $this->startTime));

        if ($error) {
            $node->appendChild($error->asNode($document));
        }

        $rootNode->appendChild($node);
        $document->appendChild($rootNode);
        return $document;
    }
}

