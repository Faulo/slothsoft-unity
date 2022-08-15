<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityPackage;
use Slothsoft\Unity\Assets\ExecutableBase;

abstract class PackageExecutableBase extends ExecutableBase implements ExecutableBuilderStrategyInterface {

    /** @var string */
    protected string $packageDirectory;

    /** @var UnityPackage */
    protected ?UnityPackage $package;

    protected function parseArguments(FarahUrlArguments $args): void {
        $this->packageDirectory = $args->get('package');
    }

    protected function validate(): void {
        if (! is_dir($this->packageDirectory)) {
            throw ExecutionError::Error('AssertDirectory', "Workspace '{$this->packageDirectory}' is not a directory!");
        }

        $this->packageDirectory = realpath($this->packageDirectory);

        $hub = UnityHub::getInstance();

        if (! $hub->isInstalled()) {
            throw ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
        }

        $this->package = $hub->findPackage($this->packageDirectory);

        if (! $this->package) {
            throw ExecutionError::Error('AssertPackage', "Workspace '{$this->packageDirectory}' does not contain a Unity package!");
        }

        if (! $this->package->ensureEditorIsInstalled()) {
            throw ExecutionError::Error('AssertEditor', "Editor installation for package '{$this->package}' failed!");
        }
    }

    protected function getExecutablePackage(): string {
        return 'ContinuousIntegration.' . preg_replace('~[^a-zA-Z0-9]~', '', basename($this->packageDirectory));
    }
}

