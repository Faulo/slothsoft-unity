<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use Symfony\Component\Process\Process;
use Slothsoft\Core\Calendar\Seconds;
use Slothsoft\Unity\MailboxAccess;
use DateInterval;
use DateTimeImmutable;

class SteamCMD {

    public const STEAM_CREDENTIALS_USR = 'STEAM_CREDENTIALS_USR';

    public const STEAM_CREDENTIALS_PSW = 'STEAM_CREDENTIALS_PSW';

    private const STEAMCMD_BIN = 'steamcmd';

    private const STEAM_EMAIL = 'noreply@steampowered.com';

    private const STEAM_2FA_PATTERN = '/\b([A-Z0-9]{5})\b/';

    public ?MailboxAccess $mailbox = null;

    private static function createLogin(string $name, string $password = '', string $code = ''): Process {
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

    private function reportError(Process $process): void {
        fwrite(STDERR, $process->getCommandLine());
        fwrite(STDERR, PHP_EOL);
        fwrite(STDERR, $process->getOutput());
    }

    public function login(string $name, string $password = ''): bool {
        $process = self::createLogin($name, $password);
        $startTime = new DateTimeImmutable();

        $resultCode = $process->run();
        $isLoggedIn = $resultCode === 0;

        if (! $isLoggedIn) {
            $output = $process->getOutput();
            if (strpos($output, 'set_steam_guard_code') and $this->mailbox) {
                if ($code = $this->fetchGuardCode($startTime)) {
                    $process = self::createLogin($name, $password, $code);
                    $resultCode = $process->run();
                    $isLoggedIn = $resultCode === 0;
                    if (! $isLoggedIn) {
                        $this->reportError($process);
                    }
                } else {
                    trigger_error('Steam sent a 2FA code, but we did not find it.');
                }
            } else {
                $this->reportError($process);
            }
        }

        return $isLoggedIn;
    }

    private function fetchGuardCode(DateTimeImmutable $startTime): ?string {
        $timeout = time() + 5 * Seconds::Minute;
        while (time() < $timeout) {
            sleep(10);
            if ($code = $this->mailbox->retrieveLatestBy(self::STEAM_EMAIL, $startTime, new DateInterval('PT5M'), self::STEAM_2FA_PATTERN)) {
                return $code;
            }
        }

        return null;
    }
}