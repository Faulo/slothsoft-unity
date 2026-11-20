<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Steam;

use Slothsoft\Unity\Command\CommandLineRedactor;
use Slothsoft\Unity\MailboxAccess;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use SensitiveParameter;

/**
 * Automates SteamCMD login, including optional Steam Guard code retrieval.
 *
 * @author Daniel Schulz
 * @since 2025-09-01
 */
final class SteamCMD {
    
    public const STEAM_CREDENTIALS_USR = 'STEAM_CREDENTIALS_USR';
    
    public const STEAM_CREDENTIALS_PSW = 'STEAM_CREDENTIALS_PSW';
    
    private const STEAMCMD_BIN = 'steamcmd';
    
    private const STEAM_EMAIL = 'noreply@steampowered.com';
    
    private const STEAM_2FA_PATTERN = '/\b([A-Z0-9]{5})\b/';
    
    public ?MailboxAccess $mailbox = null;
    
    private static function createLogin(string $name, #[SensitiveParameter] string $password = '', #[SensitiveParameter] string $code = ''): Process {
        $args = [];
        
        if (strlen($name)) {
            $args[] = $name;
        }
        
        if (strlen($password)) {
            $args[] = $password;
        }
        
        if (strlen($code)) {
            $args[] = $code;
        }
        
        return new Process([
            self::STEAMCMD_BIN,
            '+login',
            ...$args,
            '+quit'
        ]);
    }
    
    private function reportError(Process $process, #[SensitiveParameter] string ...$secrets): void {
        fwrite(STDERR, CommandLineRedactor::fromEnvironment(...$secrets)->redact($process->getCommandLine()));
        fwrite(STDERR, PHP_EOL);
        fwrite(STDERR, $process->getOutput());
    }
    
    /** @throws RuntimeException if SteamCMD exceeds its timeout */
    public function login(string $name, #[SensitiveParameter] string $password = ''): bool {
        $process = self::createLogin($name, $password);
        $startTime = new DateTimeImmutable();
        
        try {
            if ($process->run() === 0) {
                return true;
            }
            
            $output = $process->getOutput();
            if (! strpos($output, 'set_steam_guard_code') || $this->mailbox === null) {
                $this->reportError($process, $password);
                return false;
            }
            
            $code = $this->fetchGuardCode($startTime);
            if (! $code) {
                trigger_error('Steam sent a 2FA code, but we did not find it.');
                return false;
            }
            
            $process = self::createLogin($name, $password, $code);
            if ($process->run() === 0) {
                return true;
            }
            
            $this->reportError($process, $password, $code);
            return false;
        } catch (ProcessTimedOutException $error) {
            // The Symfony exception holds the original command line and process.
            throw new RuntimeException(sprintf('SteamCMD login exceeded the timeout of %s seconds.', $error->getExceededTimeout()));
        }
    }
    
    private function fetchGuardCode(DateTimeImmutable $startTime): ?string {
        return $this->mailbox?->waitForLatestBy(self::STEAM_EMAIL, $startTime, new DateInterval('PT5M'), self::STEAM_2FA_PATTERN);
    }
}
