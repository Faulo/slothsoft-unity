<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\MailboxAccess;

/**
 * SteamcmdTest
 *
 * @see Steamcmd
 */
class SteamCMDTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(Steamcmd::class), "Failed to load class 'Slothsoft\Unity\Steam\Steamcmd'!");
    }

    public function testLoginAnonymous(): void {
        if (! FileSystem::commandExists('steamcmd')) {
            $this->markTestSkipped('steamcmd is not available from the command line!');
            return;
        }

        $steam = new SteamCMD();

        $isLoggedIn = $steam->login('anonymous');

        $this->assertTrue($isLoggedIn);
    }

    const ENV_FILE = '.env.local';

    public function testLoginViaEnv(): void {
        if (! FileSystem::commandExists('steamcmd')) {
            $this->markTestSkipped('steamcmd is not available from the command line!');
            return;
        }

        if (is_file(self::ENV_FILE)) {
            Dotenv::createImmutable(getcwd(), self::ENV_FILE)->load();

            $user = $_ENV[SteamCMD::STEAM_CREDENTIALS_USR] ?? null;
            $password = $_ENV[SteamCMD::STEAM_CREDENTIALS_PSW] ?? null;

            if (! $user or ! $password) {
                $this->markTestSkipped('Missing env variable "' . SteamCMD::STEAM_CREDENTIALS_USR . '" and "' . SteamCMD::STEAM_CREDENTIALS_PSW . '" in "' . self::ENV_FILE . '".');
            }

            $mailUser = $_ENV[MailboxAccess::ENV_EMAIL_USR] ?? null;
            $mailPassword = $_ENV[MailboxAccess::ENV_EMAIL_PSW] ?? null;

            if (! $mailUser or ! $mailPassword) {
                $this->markTestSkipped('Missing env variable "' . MailboxAccess::ENV_EMAIL_USR . '" and "' . MailboxAccess::ENV_EMAIL_PSW . '" in "' . self::ENV_FILE . '".');
            }

            $steam = new SteamCMD();

            $steam->mailbox = new MailboxAccess($mailUser, $mailPassword);

            $isLoggedIn = $steam->login($user, $password);

            $this->assertTrue($isLoggedIn);
        } else {
            $this->markTestSkipped('Missing email login file "' . self::ENV_FILE . '".');
        }
    }
}