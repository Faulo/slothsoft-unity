<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Ds\Set;

class UnityEnvironment {
    
    public const ENV_UNITY_LOGGING = 'UNITY_LOGGING';
    
    public const UNITY_LOG_ALL = 'all';
    
    private const UNITY_LOG_DEFAULT = self::UNITY_LOG_ALL;
    
    public const UNITY_LOG_STDIN = 'stdin';
    
    public const UNITY_LOG_STDOUT = 'stdout';
    
    public const UNITY_LOG_STDERR = 'stderr';
    
    public const UNITY_LOG_LICENSE = 'license';
    
    private static function logging(): Set {
        static $logging = null;
        if ($logging === null) {
            $env = getenv(self::ENV_UNITY_LOGGING);
            if ($env === false) {
                $env = self::UNITY_LOG_DEFAULT;
            }
            
            $logging = new Set(preg_split('~\s+~', strtolower($env), -1, PREG_SPLIT_NO_EMPTY));
        }
        return $logging;
    }
    
    public static function isLoggingInput(): bool {
        return self::logging()->contains(self::UNITY_LOG_ALL) or self::logging()->contains(self::UNITY_LOG_STDIN);
    }
    
    public static function isLoggingOutput(): bool {
        return self::logging()->contains(self::UNITY_LOG_ALL) or self::logging()->contains(self::UNITY_LOG_STDOUT);
    }
    
    public static function isLoggingError(): bool {
        return self::logging()->contains(self::UNITY_LOG_ALL) or self::logging()->contains(self::UNITY_LOG_STDERR);
    }
    
    public static function isLoggingLicense(): bool {
        return self::logging()->contains(self::UNITY_LOG_ALL) or self::logging()->contains(self::UNITY_LOG_LICENSE);
    }
}