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
    protected string $message;

    /** @var UnityProject */
    protected ?UnityProject $project;

    /** @var float */
    private float $startTime;

    /** @var string */
    private string $packageName;

    /** @var string */
    private string $processName;

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
        $this->startTime = microtime(true);
        $this->packageName = substr(str_replace('/', '.', (string) $context->getUrlPath()), 1);
        $this->processName = (string) $args;

        $resultBuilder = $this->parseArguments($args) ? $this->createSuccessResult() : $this->createErrorResult();

        return new ExecutableStrategies($resultBuilder);
    }

    protected abstract function createSuccessDocument(): DOMDocument;

    protected function createSuccessResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            try {
                return $this->createSuccessDocument();
            } catch (Throwable $e) {
                $code = $e->getCode();
                if ($code === 0) {
                    $code = - 1;
                }
                return $this->createResultDocument($code, '', (string) $e, $e->getMessage());
            }
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'result.xml');
    }

    protected function createErrorResult(): ResultBuilderStrategyInterface {
        $delegate = function (): DOMDocument {
            return $this->createResultDocument(- 1, '', '', $this->message);
        };

        $writer = new DOMWriterFromDocumentDelegate($delegate);

        return new DOMWriterResultBuilder($writer, 'error.xml');
    }

    protected function createResultDocument(int $code, string $stdout, string $stderr, string $message = 'ERROR'): DOMDocument {
        $document = new DOMDocument();
        $rootNode = $document->createElement('result');

        $node = $document->createElement('process');
        $node->setAttribute('package', $this->packageName);
        $node->setAttribute('name', $this->processName);
        $node->setAttribute('result', (string) $code);
        $node->setAttribute('stdout', $stdout);
        $node->setAttribute('stderr', $stderr);
        $node->setAttribute('message', $message);
        $node->setAttribute('start-time', date(DateTime::W3C, (int) $this->startTime));
        $node->setAttribute('duration', sprintf('%0.06f', microtime(true) - $this->startTime));

        $rootNode->appendChild($node);
        $document->appendChild($rootNode);
        return $document;
    }
}

