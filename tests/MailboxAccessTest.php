<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;
use DateInterval;
use DateTimeImmutable;

/**
 * MailboxAccessTest
 *
 * @see MailboxAccess
 *
 * @todo auto-generated
 */
class MailboxAccessTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(MailboxAccess::class), "Failed to load class 'Slothsoft\Unity\MailboxAccess'!");
    }

    public function testRetrieveLatestCode() {
        if (is_file('.env.local')) {
            Dotenv::createImmutable(getcwd(), '.env.local')->load();

            if ($expected = $_ENV['EMAIL_CREDENTIALS_CODE']) {
                $sut = new MailboxAccess($_ENV[MailboxAccess::ENV_EMAIL_USR], $_ENV[MailboxAccess::ENV_EMAIL_PSW]);
                $this->assertEquals($expected, $sut->retrieveLatestBy('no-reply@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('P600M'), '/\b(\d{6})\b/'));
            } else {
                $this->markTestSkipped('Missing env variable "EMAIL_CREDENTIALS_CODE" in ".env.local".');
            }
        } else {
            $this->markTestSkipped('Missing email login file ".env.local".');
        }
    }

    public function testRetrieveNoCodeByEmail() {
        if (is_file('.env.local')) {
            Dotenv::createImmutable(getcwd(), '.env.local')->load();

            $sut = new MailboxAccess($_ENV[MailboxAccess::ENV_EMAIL_USR], $_ENV[MailboxAccess::ENV_EMAIL_PSW]);
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('P600M'), '/\b(\d{6})\b/'));
        } else {
            $this->markTestSkipped('Missing email login file ".env.local".');
        }
    }

    public function testRetrieveNoCodeByNow() {
        if (is_file('.env.local')) {
            Dotenv::createImmutable(getcwd(), '.env.local')->load();

            $sut = new MailboxAccess($_ENV[MailboxAccess::ENV_EMAIL_USR], $_ENV[MailboxAccess::ENV_EMAIL_PSW]);
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('P1M'), '/\b(\d{6})\b/'));
        } else {
            $this->markTestSkipped('Missing email login file ".env.local".');
        }
    }

    public function testRetrieveNoCodeByPattern() {
        if (is_file('.env.local')) {
            Dotenv::createImmutable(getcwd(), '.env.local')->load();

            $sut = new MailboxAccess($_ENV[MailboxAccess::ENV_EMAIL_USR], $_ENV[MailboxAccess::ENV_EMAIL_PSW]);
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('P600M'), '/\b(\d{7})\b/'));
        } else {
            $this->markTestSkipped('Missing email login file ".env.local".');
        }
    }
}