<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use InvalidArgumentException;
use Slothsoft\Core\FileSystem;

/**
 * Maps logical build targets to Unity editor modules, output names, and batchmode arguments.
 *
 * @author Daniel Schulz
 * @since 2022-07-15
 */
final class UnityBuildTarget {
    
    public const WINDOWS = 'windows';
    
    public const LINUX = 'linux';
    
    public const MAC_OSX = 'mac';
    
    public const BACKEND_MONO = 0;
    
    public const BACKEND_IL2CPP = 1;
    
    public static function getEditoModules(string $target, int $backend = self::BACKEND_MONO): array {
        return match (strtolower($target)) {
            self::WINDOWS => match ($backend) {
                self::BACKEND_MONO => PHP_OS === 'WINNT' ? [] : [
                    'windows-mono'
                ],
                self::BACKEND_IL2CPP => [
                    'windows-il2cpp'
                ],
                default => throw new InvalidArgumentException("Unkown scripting backend '$backend'"),
            },
            self::LINUX => match ($backend) {
                self::BACKEND_MONO => PHP_OS === 'Linux' ? [] : [
                    'linux-mono'
                ],
                self::BACKEND_IL2CPP => [
                    'linux-il2cpp'
                ],
                default => throw new InvalidArgumentException("Unkown scripting backend '$backend'"),
            },
            self::MAC_OSX => match ($backend) {
                self::BACKEND_MONO => [
                    'mac-mono'
                ],
                self::BACKEND_IL2CPP => [
                    'mac-il2cpp'
                ],
                default => throw new InvalidArgumentException("Unkown scripting backend '$backend'"),
            },
            default => throw new InvalidArgumentException("Unkown build target '$target'"),
        };
    }
    
    public static function getBuildExecutable(string $target, string $productName): string {
        $buildExecutable = FileSystem::filenameSanitize($productName);
        return match (strtolower($target)) {
            self::WINDOWS => "$buildExecutable.exe",
            self::LINUX => $buildExecutable,
            self::MAC_OSX => "$buildExecutable.app",
            default => throw new InvalidArgumentException($target),
        };
    }
    
    public static function getBuildParameters(string $target, string $buildExecutable): array {
        return match (strtolower($target)) {
            self::WINDOWS => [
                '-buildTarget',
                'Win64',
                '-buildWindows64Player',
                $buildExecutable
            ],
            self::LINUX => [
                '-buildTarget',
                'Linux64',
                '-buildLinux64Player',
                $buildExecutable
            ],
            self::MAC_OSX => [
                '-buildTarget',
                'OSXUniversal',
                '-buildOSXUniversalPlayer',
                $buildExecutable
            ],
            default => throw new InvalidArgumentException($target),
        };
    }
}
