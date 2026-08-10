<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * Describes the exact project root selected for package installation.
 */
final readonly class PreparedWorkspace {
    public function __construct(private string $path, private WorkspaceState $initialState, private bool $initialized) {
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getInitialState(): WorkspaceState {
        return $this->initialState;
    }

    public function wasInitialized(): bool {
        return $this->initialized;
    }
}
