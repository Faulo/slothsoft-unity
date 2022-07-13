<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Psr\Http\Message\StreamInterface;
use Slothsoft\Core\IO\Writable\Adapter\ChunkWriterFromStreamWriter;
use Slothsoft\Core\IO\Writable\Delegates\DOMWriterFromElementDelegate;
use Slothsoft\Core\IO\Writable\Delegates\StreamWriterFromStreamDelegate;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\ChunkWriterResultBuilder;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\DOMWriterResultBuilder;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Slothsoft\Unity\ZipFileStream;
use DOMDocument;
use DOMElement;

class ProjectBuildBuilder implements ExecutableBuilderStrategyInterface {

    /** @var string */
    private $message;

    /** @var UnityProject */
    private $project;

    private function parseArguments(FarahUrlArguments $args): bool {
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
            $this->message = "Editor for project '$this->project' is not licensed!";
            return false;
        }

        return true;
    }

    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        if ($this->parseArguments($args)) {
            $delegate = function (): StreamInterface {
                $path = temp_dir(__NAMESPACE__);
                
                $this->project->build($path);
                
                $zip = new ZipFileStream();
                
                $zip->addDirRecursive($path);
                
                return $zip->outputAsStream();
            };
            
            $writer = new ChunkWriterFromStreamWriter(new StreamWriterFromStreamDelegate($delegate));
            
            $resultBuilder = new ChunkWriterResultBuilder($writer, 'build.zip', false);
            
            return new ExecutableStrategies($resultBuilder);
        } else {
            $delegate = function (DOMDocument $document): DOMElement {
                $node = $document->createElement('error');
                $node->textContent = $this->message;
                return $node;
            };
            
            $writer = new DOMWriterFromElementDelegate($delegate);
            
            $resultBuilder = new DOMWriterResultBuilder($writer, 'result.xml');
            
            return new ExecutableStrategies($resultBuilder);
        }
    }
}

