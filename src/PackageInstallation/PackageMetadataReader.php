<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

use JsonException;

/**
 * Validates package.json and its filesystem-safe Unity package name.
 */
final class PackageMetadataReader implements PackageMetadataReaderInterface {
    public function read(string $packagePath): PackageMetadata {
        if ($packagePath === '' or str_contains($packagePath, "\0")) {
            throw new PackageInstallationException('Package path must not be empty or contain a null byte.');
        }

        if (! is_dir($packagePath)) {
            throw new PackageInstallationException("Package '$packagePath' is not a directory.");
        }

        $resolvedPath = realpath($packagePath);
        if ($resolvedPath === false) {
            throw new PackageInstallationException("Unable to resolve package '$packagePath'.");
        }

        $metadataPath = $resolvedPath . DIRECTORY_SEPARATOR . 'package.json';
        if (! is_file($metadataPath)) {
            throw new PackageInstallationException("Package '$resolvedPath' has no package.json.");
        }

        $json = @file_get_contents($metadataPath);
        if ($json === false) {
            throw new PackageInstallationException("Unable to read package metadata '$metadataPath'.");
        }

        try {
            $metadata = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new PackageInstallationException("Unable to parse package metadata '$metadataPath': {$exception->getMessage()}", 0, $exception);
        }

        if (! is_array($metadata) or ($metadata !== [] and array_is_list($metadata))) {
            throw new PackageInstallationException("Package metadata '$metadataPath' must contain a JSON object.");
        }

        $name = $metadata['name'] ?? null;
        $isReservedWindowsName = is_string($name) && preg_match('~\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|\z)~i', $name) === 1;
        if (! is_string($name) or ! preg_match('~\A[A-Za-z0-9](?:[A-Za-z0-9._-]*[A-Za-z0-9])?\z~', $name) or $isReservedWindowsName) {
            throw new PackageInstallationException("Package metadata '$metadataPath' has an invalid package name.");
        }

        return new PackageMetadata($resolvedPath, $name);
    }
}
