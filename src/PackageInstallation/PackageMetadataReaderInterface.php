<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

interface PackageMetadataReaderInterface {
    public function read(string $packagePath): PackageMetadata;
}
