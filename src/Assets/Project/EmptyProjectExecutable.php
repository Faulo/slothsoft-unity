<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Project;

use DOMDocument;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Assets\ExecutableBase;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityEditor;
use Slothsoft\Unity\UnityHub;

/**
 * Creates an empty Unity project using the latest final matching editor.
 */
final class EmptyProjectExecutable extends ExecutableBase {
    
    private string $workspace;
    
    private string $version;
    
    private UnityEditor $editor;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        $this->workspace = $args->get('workspace');
        $this->version = $args->get('version');
    }
    
    protected function validate(): void {
        if ($this->workspace === '') {
            throw ExecutionError::Error('AssertParameter', "Missing parameter 'workspace'!");
        }
        
        $hub = UnityHub::getInstance();
        $this->editor = $hub->getEditorByVersion($hub->inventStableEditorVersion($this->version, true));
        
        if (! $this->editor->isInstalled() and ! $this->editor->install()) {
            throw ExecutionError::Error('AssertEditor', "Failed to install {$this->editor}!");
        }
    }
    
    protected function getExecutablePackage(): string {
        $workspaceName = preg_replace('~\s+~', '', basename(rtrim($this->workspace, '/\\')));
        return 'ContinuousIntegration.Project.EmptyProject.' . ($workspaceName !== '' ? $workspaceName : 'Unknown');
    }
    
    protected function getExecutableCall(): string {
        return sprintf('CreateEmptyProject("%s", "%s")', $this->workspace, $this->version);
    }
    
    protected function createResultDocument(): ?DOMDocument {
        $this->editor->createEmptyProject($this->workspace, false);
        return null;
    }
}
