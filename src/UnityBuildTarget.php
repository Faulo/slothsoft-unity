<?php
namespace Slothsoft\Unity;

use InvalidArgumentException;

class UnityBuildTarget {

    public const WINDOWS = 'windows';

    public const LINUX = 'linux';

    public const MAC_OSX = 'mac_os';

    public static function getEditoModules(string $target): array {
        switch (strtolower($target)) {
            case self::WINDOWS:
                return [
                    'windows',
                    'windows-mono'
                ];
            case self::LINUX:
                return [
                    'linux-mono',
                    'linux-il2cpp'
                ];
            case self::MAC_OSX:
                return [
                    'mac-mono',
                    'mac-il2cpp'
                ];
            default:
                throw new InvalidArgumentException($target);
        }
    }

    public static function getBuildParameters(string $target, string $buildPath): array {
        switch (strtolower($target)) {
            case self::WINDOWS:
                return [
                    '-buildTarget',
                    'Win64',
                    '-buildWindows64Player',
                    "$buildPath.exe"
                ];
            case self::LINUX:
                return [
                    '-buildTarget',
                    'Linux64',
                    '-buildLinux64Player',
                    $buildPath
                ];
            case self::MAC_OSX:
                return [
                    '-buildTarget',
                    'OSXUniversal',
                    '-buildOSXUniversalPlayer',
                    "$buildPath.app"
                ];
            default:
                throw new InvalidArgumentException($target);
        }
    }
}

