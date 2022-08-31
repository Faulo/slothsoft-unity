<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityProject;
use Slothsoft\Unity\Assets\ExecutableBase;

abstract class ProjectExecutableBase extends ExecutableBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $workspace;

    /** @var string */
    protected string $workspaceName;

    /** @var UnityProject */
    protected ?UnityProject $project;

    protected function parseArguments(FarahUrlArguments $args): void {
        $this->workspace = $args->get('workspace');
        $this->workspaceName = preg_replace('~\s+~', '', basename($this->workspace));
    }

    protected function validate(): void {
        if (! is_dir($this->workspace)) {
            throw ExecutionError::Error('AssertDirectory', "Workspace '{$this->workspace}' is not a directory!");
        }

        $this->workspace = realpath($this->workspace);

        $hub = UnityHub::getInstance();

        if (! $hub->isInstalled()) {
            throw ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
        }

        $this->project = $hub->findProject($this->workspace);

        if (! $this->project) {
            throw ExecutionError::Error('AssertProject', "Workspace '{$this->workspace}' does not contain a Unity project!");
        }

        if (! $this->project->ensureEditorIsInstalled()) {
            throw ExecutionError::Error('AssertEditor', "Editor installation for project '{$this->project}' failed!");
        }

        if (! $this->project->ensureEditorIsLicensed()) {
            throw ExecutionError::Error('AssertLicense', "Editor for project '{$this->project}' is not licensed! Visit https://license.unity3d.com/manual for manual activation of a license for editor version '{$this->project->getEditorVersion()}'.");
        }
    }
}

