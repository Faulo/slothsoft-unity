<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

use Throwable;

/**
 * Reuses exact project roots and delegates Unity-backed project creation.
 */
final readonly class WorkspacePreparer {
    public function __construct(private WorkspaceClassifier $classifier, private WorkspaceInitializerInterface $initializer) {
    }

    public function prepare(string $path): PreparedWorkspace {
        $classification = $this->classifier->classify($path);
        $state = $classification->getState();

        if ($state === WorkspaceState::VALID_PROJECT) {
            return new PreparedWorkspace($path, $state, false);
        }

        if (! $classification->isInitializable()) {
            throw new PackageInstallationException("Workspace '$path' is neither missing, empty, nor an exact Unity project root.");
        }

        try {
            $this->initializer->initialize($path);
        } catch (Throwable $exception) {
            throw new PackageInstallationException("Unable to initialize Unity workspace '$path': {$exception->getMessage()}", 0, $exception);
        }

        $prepared = $this->classifier->classify($path);
        if ($prepared->getState() !== WorkspaceState::VALID_PROJECT) {
            throw new PackageInstallationException("Workspace initializer did not create a valid Unity project at exact path '$path'.");
        }

        return new PreparedWorkspace($path, $state, true);
    }
}
