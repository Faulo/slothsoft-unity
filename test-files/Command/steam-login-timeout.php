<?php
declare(strict_types = 1);

use Slothsoft\Unity\MailboxAccess;
use Slothsoft\Unity\Steam\SteamCMD;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

final class SteamLoginProcessFixture {
    
    /** @param list<string> $arguments */
    public function __construct(private readonly array $arguments) {
    }
    
    /** @throws JsonException */
    public function run(): int {
        fwrite(STDOUT, json_encode($this->arguments, JSON_THROW_ON_ERROR) . PHP_EOL);
        
        if (SteamLoginMailboxFixture::hasCredentials() && count($this->arguments) === 5) {
            return 1;
        }
        
        // The subprocess registers this fixture as Symfony's Process class.
        /** @noinspection PhpParamsInspection */
        throw new ProcessTimedOutException($this, ProcessTimedOutException::TYPE_GENERAL);
    }
    
    public function getOutput(): string {
        return 'Steam requests set_steam_guard_code';
    }
    
    public function getCommandLine(): string {
        return implode(' ', $this->arguments);
    }
    
    public function getTimeout(): float {
        return 7.5;
    }
}

final class SteamLoginMailboxFixture {
    
    public static function hasCredentials(): bool {
        return getenv('TEST_STEAM_GUARD_CODE') !== false;
    }
    
    public function waitForLatestBy(): string {
        return (string) getenv('TEST_STEAM_GUARD_CODE');
    }
}

class_alias(SteamLoginProcessFixture::class, Process::class);
class_alias(SteamLoginMailboxFixture::class, MailboxAccess::class);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

if ($argv[1] === 'cli') {
    $_SERVER['argc'] = 1;
    require dirname(__DIR__, 2) . '/scripts/steam-login';
    return;
}

$steam = new SteamCMD();
if (SteamLoginMailboxFixture::hasCredentials()) {
    $steam->mailbox = new MailboxAccess();
}

try {
    $steam->login('test-user', (string) getenv('TEST_STEAM_PASSWORD'));
} catch (RuntimeException $error) {
    fwrite(STDERR, (string) $error);
    if ($error->getPrevious() !== null) {
        exit(2);
    }
    fwrite(STDERR, PHP_EOL . print_r($error->getTrace(), true));
    exit(1);
}
