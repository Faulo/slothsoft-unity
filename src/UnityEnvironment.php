<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Ds\Set;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

final class UnityEnvironment {
    
    public const ENV_UNITY_NO_GRAPHICS = 'UNITY_NO_GRAPHICS';
    
    public static function getNoGraphics(): bool {
        return (bool) (int) getenv(self::ENV_UNITY_NO_GRAPHICS);
    }
    
    public const ENV_UNITY_ACCELERATOR_ENDPOINT = 'UNITY_ACCELERATOR_ENDPOINT';
    
    public const ENV_UNITY_ACCELERATOR_PARAMS = 'UNITY_ACCELERATOR_PARAMS';
    
    public static function getAcceleratorEndpoint(): ?string {
        return getenv(self::ENV_UNITY_ACCELERATOR_ENDPOINT) ?: null;
    }
    
    public static function getAcceleratorParams(): array {
        $params = getenv(self::ENV_UNITY_ACCELERATOR_PARAMS);
        return $params ? preg_split('~\s+~', $params, - 1, PREG_SPLIT_NO_EMPTY) : [];
    }
    
    public const ENV_UNITY_LOGGING = 'UNITY_LOGGING';
    
    public const UNITY_LOG_ALL = 'all';
    
    public const UNITY_LOG_NONE = 'none';
    
    private const UNITY_LOG_DEFAULT = 'stderr';
    
    public const UNITY_LOG_STDIN = 'stdin';
    
    public const UNITY_LOG_STDOUT = 'stdout';
    
    public const UNITY_LOG_STDERR = 'stderr';
    
    public const UNITY_LOG_LICENSE = 'licensor';
    
    public const UNITY_LOG_CACHE = 'cache';
    
    private static ?Set $logging = null;
    
    private static function logging(): Set {
        if (self::$logging === null) {
            $env = getenv(self::ENV_UNITY_LOGGING);
            if ($env === false) {
                $env = self::UNITY_LOG_DEFAULT;
            }
            
            self::$logging = new Set(preg_split('~\s+~', strtolower($env), - 1, PREG_SPLIT_NO_EMPTY));
        }
        return self::$logging;
    }
    
    public static function reload(): void {
        self::$logging = null;
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
    
    public static function isLoggingCache(): bool {
        return self::logging()->contains(self::UNITY_LOG_ALL) or self::logging()->contains(self::UNITY_LOG_CACHE);
    }
    
    private static ?OutputFormatter $formatter = null;
    
    private static function formatter(): OutputFormatter {
        if (self::$formatter === null) {
            self::$formatter = new OutputFormatter(decorated: true);
            self::$formatter->setStyle(self::UNITY_LOG_STDIN, new OutputFormatterStyle('cyan'));
            self::$formatter->setStyle(self::UNITY_LOG_STDOUT, new OutputFormatterStyle('gray'));
            self::$formatter->setStyle(self::UNITY_LOG_STDERR, new OutputFormatterStyle('red'));
            self::$formatter->setStyle(self::UNITY_LOG_LICENSE, new OutputFormatterStyle('yellow'));
            self::$formatter->setStyle(self::UNITY_LOG_CACHE, new OutputFormatterStyle('blue'));
        }
        
        return self::$formatter;
    }
    
    private static function format(string $text, string $style) {
        return self::formatter()->format("<$style>$text</$style>");
    }
    
    public static function formatInput(string $text): string {
        return self::format($text, self::UNITY_LOG_STDIN);
    }
    
    public static function formatOutput(string $text): string {
        return self::format($text, self::UNITY_LOG_STDOUT);
    }
    
    public static function formatError(string $text): string {
        return self::format($text, self::UNITY_LOG_STDERR);
    }
    
    public static function formatLicensor(string $text): string {
        return self::format($text, self::UNITY_LOG_LICENSE);
    }
    
    public static function formatCache(string $text): string {
        return self::format($text, self::UNITY_LOG_CACHE);
    }
}