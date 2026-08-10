<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * Creates a Unity project at the exact requested workspace path.
 */
interface WorkspaceInitializerInterface {
    public function initialize(string $workspacePath): void;
}
