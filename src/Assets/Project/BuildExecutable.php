<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\FileSystem;
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

    protected function validate(): ?ExecutionError {
        if ($this->target === '') {
            return ExecutionError::Error('AssertParameter', "Missing parameter 'target'!");
        }

        if ($this->path === '') {
            return ExecutionError::Error('AssertParameter', "Missing parameter 'path'!");
        }

        return parent::validate();
    }

    protected function getExecutableCall(): string {
        return sprintf('Build("%s")', $this->target);
    }

    protected function createSuccessDocument(): DOMDocument {
        $result = $this->project->build($this->target, $this->path);
        $code = $result->getExitCode();
        $stdout = $result->getOutput();
        $stderr = $result->getErrorOutput();
        $error = null;

        if ($code === 0 and count(FileSystem::scanDir($this->path)) === 0) {
            $code = - 1;
        }

        if ($code !== 0) {
            $error = ExecutionError::Failure('AssertBuild', 'Build failed!');
        }

        return $this->createResultDocument($code, $stdout, $stderr, $error);
    }
}

