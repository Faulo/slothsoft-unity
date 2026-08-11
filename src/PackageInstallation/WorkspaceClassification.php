<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * The result of examining one workspace path without searching around it.
 */
final readonly class WorkspaceClassification {
    public function __construct(private string $path, private WorkspaceState $state) {
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getState(): WorkspaceState {
        return $this->state;
    }

    public function isInitializable(): bool {
        return $this->state === WorkspaceState::MISSING or $this->state === WorkspaceState::EMPTY;
    }
}
