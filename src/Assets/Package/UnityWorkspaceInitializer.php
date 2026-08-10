<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\PackageInstallation\PackageInstallationException;
use Slothsoft\Unity\PackageInstallation\WorkspaceInitializerInterface;
use Slothsoft\Unity\UnityEditor;

/**
 * Creates a filesystem-backed empty project for a package workspace.
 */
final readonly class UnityWorkspaceInitializer implements WorkspaceInitializerInterface {
    
    public function __construct(private ?UnityEditor $editor) {
    }
    
    public function initialize(string $workspacePath): void {
        if ($this->editor === null) {
            throw new PackageInstallationException('No Unity editor was selected for workspace initialization.');
        }
        if (! $this->editor->isInstalled() and ! $this->editor->install()) {
            throw ExecutionError::Error('AssertEditor', "Failed to install {$this->editor}!");
        }
        
        $this->editor->createEmptyProject($workspacePath, false);
    }
}
