<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * Exact-root states accepted by the workspace-first installation flow.
 */
enum WorkspaceState: string {
    case MISSING = 'missing';
    case EMPTY = 'empty';
    case VALID_PROJECT = 'valid-project';
    case INVALID = 'invalid';
}
