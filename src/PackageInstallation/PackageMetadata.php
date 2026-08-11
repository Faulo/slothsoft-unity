<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

/**
 * Validated metadata used to derive an embedded package destination.
 */
final readonly class PackageMetadata {
    public function __construct(private string $path, private string $name) {
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getName(): string {
        return $this->name;
    }
}
