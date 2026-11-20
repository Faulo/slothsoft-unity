<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Slothsoft\Unity\MailboxAccess;
use Slothsoft\Unity\Steam\SteamCMD;
use Slothsoft\Unity\UnityLicensor;
use Symfony\Component\Process\Process;
use SensitiveParameter;

/** Masks known passwords and additional secrets in command-line diagnostics. */
final readonly class CommandLineRedactor {
    
    public const MARKER = '[REDACTED]';
    
    /** @param array<string, string> $replacements */
    private function __construct(private array $replacements) {
    }
    
    public static function fromEnvironment(#[SensitiveParameter] string ...$secrets): self {
        foreach ([
                     UnityLicensor::ENV_UNITY_LICENSE_PASSWORD,
                     SteamCMD::STEAM_CREDENTIALS_PSW,
                     MailboxAccess::ENV_EMAIL_PSW
                 ] as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $secrets[] = $value;
            }
        }
        
        $replacements = [];
        foreach ($secrets as $value) {
            if ($value !== '') {
                $replacements[$value] = self::MARKER;
                $replacements[(new Process([$value]))->getCommandLine()] = self::MARKER;
            }
        }
        return new self($replacements);
    }
    
    public function redact(string $commandLine): string {
        return strtr($commandLine, $this->replacements);
    }
}
