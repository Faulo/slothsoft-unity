<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use Slothsoft\Unity\UnityPackage;
use Slothsoft\Unity\Assets\ExecutableBase;

/**
 * Base strategy for Farah executables that operate on Unity packages.
 *
 * @author Daniel Schulz
 * @since 2022-08-15
 */
abstract class PackageExecutableBase extends ExecutableBase implements ExecutableBuilderStrategyInterface {
    
    protected string $packageDirectory;
    
    protected string $packageName;
    
    protected ?UnityPackage $package;
    
    protected function parseArguments(FarahUrlArguments $args): void {
        $this->packageDirectory = $args->get('package');
        $name = basename($this->packageDirectory);
        $this->packageName = preg_replace('~\s+~', '', $name) ?? $name;
    }
    
    protected function validate(): void {
        $directory = realpath($this->packageDirectory);
        if ($directory === false or ! is_dir($directory)) {
            throw ExecutionError::Error('AssertDirectory', "Workspace '$this->packageDirectory' is not a directory!");
        }
        
        $this->packageDirectory = $directory;
        
        $hub = UnityHub::getInstance();
        
        if (! $hub->isInstalled()) {
            throw ExecutionError::Error('AssertHub', "Failed to find Unity Hub!");
        }
        
        $this->package = $hub->findPackage($this->packageDirectory);
        
        if (! $this->package) {
            throw ExecutionError::Error('AssertPackage', "Workspace '$this->packageDirectory' does not contain a Unity package!");
        }
        
        if (! $this->package->ensureEditorIsInstalled()) {
            throw ExecutionError::Error('AssertEditor', "Editor installation for package '$this->package' failed!");
        }
    }
}
