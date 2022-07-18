<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use InvalidArgumentException;

class UnityBuildTarget {

    public const WINDOWS = 'windows';

    public const LINUX = 'linux';

    public const MAC_OSX = 'mac_os';

    public const BACKEND_MONO = 0;

    public const BACKEND_IL2CPP = 1;

    public static function getEditoModules(string $target, int $backend = self::BACKEND_MONO): array {
        switch (strtolower($target)) {
            case self::WINDOWS:
                switch ($backend) {
                    case self::BACKEND_MONO:
                        return [
                            'windows-mono'
                        ];
                    case self::BACKEND_IL2CPP:
                        return [
                            'windows-il2cpp'
                        ];
                    default:
                        throw new InvalidArgumentException("Unkown scripting backend '$backend'");
                }
            case self::LINUX:
                switch ($backend) {
                    case self::BACKEND_MONO:
                        return [
                            'linux-mono'
                        ];
                    case self::BACKEND_IL2CPP:
                        return [
                            'linux-il2cpp'
                        ];
                    default:
                        throw new InvalidArgumentException("Unkown scripting backend '$backend'");
                }
            case self::MAC_OSX:
                switch ($backend) {
                    case self::BACKEND_MONO:
                        return [
                            'mac-mono'
                        ];
                    case self::BACKEND_IL2CPP:
                        return [
                            'mac-il2cpp'
                        ];
                    default:
                        throw new InvalidArgumentException("Unkown scripting backend '$backend'");
                }
            default:
                throw new InvalidArgumentException("Unkown build target '$target'");
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

