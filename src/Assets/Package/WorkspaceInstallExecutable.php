<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Package;

use DOMDocument;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Assets\ExecutableBase;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\PackageInstallation\EmbeddedPackageReplacer;
use Slothsoft\Unity\PackageInstallation\ManifestFileManager;
use Slothsoft\Unity\PackageInstallation\PackageInstallationException;
use Slothsoft\Unity\PackageInstallation\PackageMetadataReader;
use Slothsoft\Unity\PackageInstallation\WorkspaceClassifier;
use Slothsoft\Unity\PackageInstallation\WorkspacePreparer;
use Slothsoft\Unity\PackageInstallation\WorkspaceState;
use Slothsoft\Unity\UnityEditor;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityPackage;
use Slothsoft\Unity\UnityPackageInfo;
use Throwable;

/**
 * Installs an embedded package into a missing, empty, or exact project root.
 */
final class WorkspaceInstallExecutable extends ExecutableBase {
    
    private string $workspace;
    
    private string $packageDirectory;
    
    private string $packageName = 'Unknown';
    
    private ?UnityEditor $editor = null;
    
    private string $installationManifest;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        $this->workspace = $args->get('workspace');
        $this->packageDirectory = $args->get('package');
    }
    
    protected function validate(): void {
        if ($this->workspace === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'workspace'!");
        }
        if ($this->packageDirectory === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'package'!");
        }
        
        $metadata = (new PackageMetadataReader())->read($this->packageDirectory);
        $this->packageDirectory = $metadata->getPath();
        $this->packageName = $metadata->getName();
        $this->installationManifest = UnityPackage::getEmptyManifestFile();
        $manifestManager = new ManifestFileManager();
        $manifestManager->read($this->installationManifest);
        
        $classification = (new WorkspaceClassifier())->classify($this->workspace);
        if ($classification->getState() === WorkspaceState::INVALID) {
            throw ExecutionError::Error('AssertWorkspace', "Workspace '{$this->workspace}' is neither missing, empty, nor an exact Unity project root.");
        }
        if ($classification->getState() === WorkspaceState::VALID_PROJECT) {
            $manifestManager->mergeFiles($this->manifestPath(), $this->installationManifest);
            return;
        }
        
        $packageInfo = UnityPackageInfo::find($this->packageDirectory);
        if ($packageInfo === null) {
            throw ExecutionError::Error('AssertPackage', "Workspace '{$this->packageDirectory}' does not contain a Unity package!");
        }
        
        $hub = UnityHub::getInstance();
        if (! $hub->isInstalled()) {
            throw ExecutionError::Error('AssertHub', 'Failed to find Unity Hub!');
        }
        $version = $hub->inventStableEditorVersion($packageInfo->getMinEditorVersion());
        $this->editor = $hub->getEditorByVersion($version);
    }
    
    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.Package.' . $this->packageName;
    }
    
    protected function getExecutableCall(): string {
        return sprintf('InstallPackage("%s", "%s")', $this->packageDirectory, $this->workspace);
    }
    
    protected function createResultDocument(): ?DOMDocument {
        $classifier = new WorkspaceClassifier();
        $preparer = new WorkspacePreparer($classifier, new UnityWorkspaceInitializer($this->editor));
        $preparer->prepare($this->workspace);
        
        $manifestManager = new ManifestFileManager();
        $manifest = $manifestManager->mergeFiles($this->manifestPath(), $this->installationManifest);
        $stagedManifest = $manifestManager->stage($this->manifestPath(), $manifest);
        $replacer = new EmbeddedPackageReplacer($classifier);
        $manifestCommitted = false;
        try {
            $replacer->prepare($this->workspace, $this->packageDirectory);
            $replacer->activate();
            $manifestManager->commit($stagedManifest);
            $manifestCommitted = true;
            $replacer->finalize();
        } catch (Throwable $exception) {
            $manifestManager->discard($stagedManifest);
            if (! $manifestCommitted) {
                try {
                    $replacer->abort();
                } catch (Throwable $rollbackException) {
                    throw new PackageInstallationException("Package installation failed and rollback was unsuccessful: {$rollbackException->getMessage()}", 0, $exception);
                }
            }

            if ($exception instanceof PackageInstallationException) {
                throw $exception;
            }
            throw new PackageInstallationException("Unable to install package '{$this->packageName}': {$exception->getMessage()}", 0, $exception);
        }
        
        return null;
    }
    
    private function manifestPath(): string {
        return $this->workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json';
    }
}
