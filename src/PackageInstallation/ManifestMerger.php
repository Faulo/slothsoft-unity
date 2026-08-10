<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\PackageInstallation;

use stdClass;

/**
 * Deterministically combines two decoded Unity manifest objects.
 *
 * stdClass is deliberately retained for JSON objects. Converting objects to
 * associative arrays would make empty objects indistinguishable from empty
 * lists and would turn objects with sequential numeric keys into lists.
 *
 * Conflict rules:
 *
 * - objects merge structurally and installation-provided scalar values and
 *   object-key values win conflicts;
 * - dependencies merge by package name and installation-provided versions win;
 * - general lists retain existing order, append installation values, and remove
 *   exactly equal values without coercing their JSON types;
 * - scoped registries merge by exact URL, installation-provided fields win, and
 *   scopes are unioned in order without exact duplicates.
 */
final class ManifestMerger {
    public function merge(stdClass $existing, stdClass $incoming): stdClass {
        return $this->mergeObjects($existing, $incoming);
    }

    private function mergeObjects(stdClass $existing, stdClass $incoming): stdClass {
        $result = new stdClass();

        foreach ($existing as $key => $value) {
            $result->{$key} = $this->normalizeValue($value, $key);
        }

        foreach ($incoming as $key => $value) {
            if (property_exists($result, $key)) {
                $result->{$key} = $this->mergeValues($result->{$key}, $value, $key);
            } else {
                $result->{$key} = $this->normalizeValue($value, $key);
            }
        }

        return $result;
    }

    private function mergeValues(mixed $existing, mixed $incoming, ?string $key = null): mixed {
        if ($key === 'dependencies' and $existing instanceof stdClass and $incoming instanceof stdClass) {
            return $this->mergeDependencies($existing, $incoming);
        }

        if ($key === 'scopedRegistries' and is_array($existing) and is_array($incoming)) {
            return $this->mergeScopedRegistries($existing, $incoming);
        }

        if ($existing instanceof stdClass and $incoming instanceof stdClass) {
            return $this->mergeObjects($existing, $incoming);
        }

        if (is_array($existing) and is_array($incoming)) {
            return $this->mergeLists($existing, $incoming);
        }

        return $this->normalizeValue($incoming, $key);
    }

    private function normalizeValue(mixed $value, ?string $key = null): mixed {
        if ($value instanceof stdClass) {
            if ($key === 'dependencies') {
                return $this->mergeDependencies(new stdClass(), $value);
            }

            return $this->mergeObjects(new stdClass(), $value);
        }

        if (is_array($value)) {
            if ($key === 'scopedRegistries') {
                return $this->mergeScopedRegistries([], $value);
            }

            return $this->mergeLists([], $value);
        }

        return $value;
    }

    private function mergeDependencies(stdClass $existing, stdClass $incoming): stdClass {
        $result = new stdClass();

        foreach ($existing as $packageName => $version) {
            $result->{$packageName} = $this->normalizeValue($version);
        }

        foreach ($incoming as $packageName => $version) {
            $result->{$packageName} = $this->normalizeValue($version);
        }

        return $result;
    }

    private function mergeLists(array $existing, array $incoming): array {
        $result = [];

        foreach ([...$existing, ...$incoming] as $value) {
            $value = $this->normalizeValue($value);
            if (! $this->containsExactValue($result, $value)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    private function mergeScopedRegistries(array $existing, array $incoming): array {
        $result = [];
        $registryIndexes = [];

        foreach ([...$existing, ...$incoming] as $registry) {
            $registry = $this->normalizeValue($registry);
            $url = $this->getRegistryUrl($registry);

            if ($url === null) {
                if (! $this->containsExactValue($result, $registry)) {
                    $result[] = $registry;
                }
                continue;
            }

            $urlKey = "\0$url";
            if (! array_key_exists($urlKey, $registryIndexes)) {
                $registryIndexes[$urlKey] = count($result);
                $result[] = $registry;
                continue;
            }

            $index = $registryIndexes[$urlKey];
            $result[$index] = $this->mergeRegistry($result[$index], $registry);
        }

        return $result;
    }

    private function containsExactValue(array $values, mixed $candidate): bool {
        foreach ($values as $value) {
            if ($this->valuesAreExactlyEqual($value, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function valuesAreExactlyEqual(mixed $left, mixed $right): bool {
        if ($left instanceof stdClass and $right instanceof stdClass) {
            $leftValues = get_object_vars($left);
            $rightValues = get_object_vars($right);
            if (array_keys($leftValues) !== array_keys($rightValues)) {
                return false;
            }
            foreach ($leftValues as $key => $value) {
                if (! $this->valuesAreExactlyEqual($value, $rightValues[$key])) {
                    return false;
                }
            }

            return true;
        }

        if (is_array($left) and is_array($right)) {
            if (array_keys($left) !== array_keys($right)) {
                return false;
            }
            foreach ($left as $key => $value) {
                if (! $this->valuesAreExactlyEqual($value, $right[$key])) {
                    return false;
                }
            }

            return true;
        }

        return $left === $right;
    }

    private function getRegistryUrl(mixed $registry): ?string {
        if (! ($registry instanceof stdClass) or ! property_exists($registry, 'url') or ! is_string($registry->url)) {
            return null;
        }

        return $registry->url === '' ? null : $registry->url;
    }

    private function mergeRegistry(stdClass $existing, stdClass $incoming): stdClass {
        $result = $this->mergeObjects(new stdClass(), $existing);

        foreach ($incoming as $key => $value) {
            if ($key !== 'scopes') {
                $result->{$key} = $this->normalizeValue($value, $key);
            }
        }

        $existingHasScopes = property_exists($existing, 'scopes');
        $incomingHasScopes = property_exists($incoming, 'scopes');
        if (! $existingHasScopes and ! $incomingHasScopes) {
            return $result;
        }

        if ($existingHasScopes and $incomingHasScopes and is_array($existing->scopes) and is_array($incoming->scopes)) {
            $result->scopes = $this->mergeLists($existing->scopes, $incoming->scopes);
        } elseif ($incomingHasScopes) {
            $result->scopes = $this->normalizeValue($incoming->scopes, 'scopes');
        } else {
            $result->scopes = $this->normalizeValue($existing->scopes, 'scopes');
        }

        return $result;
    }
}
