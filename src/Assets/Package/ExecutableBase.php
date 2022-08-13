<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromDocumentDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ResultBuilderStrategyInterface;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityPackage;
use Slothsoft\Unity\UnityProject;
use Slothsoft\Unity\Assets\ExecutionError;
use DOMDocument;
use DateTime;
use Throwable;

abstract class ExecutableBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $packageDirectory;

    /** @var string */
    protected string $workspace;

    /** @var UnityPackage */
    protected ?UnityPackage $package;

    /** @var UnityProject */
    protected ?UnityProject $project;

    /** @var float */
    private float $startTime;

    /** @var ExecutionError */
    private ?ExecutionError $error;

    protected function parseArguments(FarahUrlArguments $args): void {
        $this->packageDirectory = $args->get('package');
        $this->workspace = $args->get('workspace');
    }

    protected function validate(): ?ExecutionError {
        if (! is_dir($this->packageDirectory)) {
            return ExecutionError::Error('AssertDirectory', "Workspace '{$this->packageDirectory}' is not a directory!");
        }

        $this->packageDirectory = realpath($this->packageDirectory);

        $hub = UnityHub::getInstance();

        if (! $hub->isInstalled()) {
            return ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
        }

        $this->package = $hub->findPackage($this->packageDirectory);

        if (! $this->package) {
            return ExecutionError::Error('AssertPackage', "Workspace '{$this->packageDirectory}' does not contain a Unity package!");
        }

        if (! $this->package->ensureEditorIsInstalled()) {
            return ExecutionError::Error('AssertEditor', "Editor installation for package '{$this->package}' failed!");
        }

        if (! is_dir($this->workspace)) {
            mkdir($this->workspace, 0777, true);
        }
        $this->workspace = realpath($this->workspace);

        if (! $this->package->ensureEditorIsLicensed($this->workspace)) {
            return ExecutionError::Error('AssertLicense', "Editor for package '{$this->package}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->package->getEditorVersion()}'.");
        }

        $this->project = $this->package->createEmptyProject($this->workspace);

        $this->workspace = $this->project->getProjectPath();

        if (! $this->project->ensureEditorIsInstalled()) {
            return ExecutionError::Error('AssertEditor', "Editor installation for package '{$this->package}' failed!");
        }

        if (! $this->project->ensureEditorIsLicensed()) {
            return ExecutionError::Error('AssertLicense', "Editor for package '{$this->package}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->project->getEditorVersion()}'.");
        }

        return null;
    }

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $this->startTime = microtime(true);

        $this->parseArguments($args);

        try {
            $this->error = $this->validate();
        } catch (Throwable $e) {
            $this->error = ExecutionError::Error(get_class($e), $e->getMessage(), $e->getTraceAsString());
        }

        $resultBuilder = $this->error ? $this->createErrorResult() : $this->createSuccessResult();

        return new ExecutableStrategies($resultBuilder);
    }

    protected abstract function createSuccessDocument(): DOMDocument;

    private function getExecutablePackage(): string {
        return 'ContinuousIntegration.' . preg_replace('~[^a-zA-Z0-9]~', '', basename($this->workspace));
    }

    protected abstract function getExecutableCall(): string;

    private function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            try {
                return $this->createSuccessDocument();
            } catch (Throwable $e) {
                $code = $e->getCode();
                if ($code === 0) {
                    $code = - 1;
                }
                return $this->createResultDocument($code, '', (string) $e, ExecutionError::Error(get_class($e), $e->getMessage(), $e->getTraceAsString()));
            }
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }

    private function createErrorResult(): ResultBuilderStrategyInterface {
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

