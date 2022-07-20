<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use DOMDocument;

class BuildExecutable extends ExecutableBase {

    /** @var string */
    private string $target;

    /** @var string */
    private string $path;

    protected function parseArguments(FarahUrlArguments $args): void {
        parent::parseArguments($args);

        $this->target = $args->get('target');
        $this->path = $args->get('path');
    }

    protected function validate(): bool {
        if ($this->target === '') {
            $this->message = "Missing parameter 'target'!";
            return false;
        }

        if ($this->path === '') {
            $this->message = "Missing parameter 'path'!";
            return false;
        }

        return parent::validate();
    }

    protected function getExecutablePackage(): string {
        return 'Slothsoft.Unity.Project.Build';
    }

    protected function getExecutableCall(): string {
        return sprintf('Build("%s", "%s", "%s")', $this->workspace, $this->path, $this->target);
    }

    protected function createSuccessDocument(): DOMDocument {
        $result = $this->project->build($this->target, $this->path);
        return $this->createResultDocument($result->getExitCode(), $result->getOutput(), $result->getErrorOutput());
    }
}

