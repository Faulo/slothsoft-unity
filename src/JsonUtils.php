<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Loads and saves JSON files used by Unity project and package metadata.
 *
 * @author Daniel Schulz
 * @since 2024-06-30
 */
final class JsonUtils {
    
    /** @return array<array-key, mixed> */
    public static function load(string $path): array {
        if (! is_file($path)) {
            throw new FileNotFoundException($path);
        }
        
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw ExecutionError::Error('JSON', "Unable to read JSON file '$path'.");
        }
        $result = json_decode($contents, true);
        if (! is_array($result)) {
            throw ExecutionError::Error('JSON', "Unable to parse ostensible JSON file '$path':" . PHP_EOL . $contents);
        }
        
        return $result;
    }
    
    /** @param array<array-key, mixed> $data */
    public static function save(string $path, array $data, int $tabLength = 4, string $eot = ''): void {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        
        if ($tabLength !== 4) {
            $json = str_replace('    ', str_pad('', $tabLength, ' '), $json);
        }
        
        $json .= $eot;
        
        file_put_contents($path, $json);
    }
}
