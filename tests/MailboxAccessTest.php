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
 */
class MailboxAccessTest extends TestCase {

    private const ENV_FILE = '.env.local';

    private function tryPrepareEnvironment(string ...$variables): bool {
        if (is_file(self::ENV_FILE)) {
            Dotenv::createImmutable(getcwd(), self::ENV_FILE)->load();

            foreach ($variables as $variable) {
                if (isset($_ENV[$variable])) {
                    putenv($variable . '=' . $_ENV[$variable]);
                }
            }
        }

        $missing = [];

        foreach ($variables as $variable) {
            if (! getenv($variable)) {
                $missing[] = $variable;
            }
        }

        if ($missing) {
            $this->markTestSkipped(sprintf('Missing environment variables [%s]', implode(', ', $missing)));
            return false;
        } else {
            return true;
        }
    }

    public function testClassExists(): void {
        $this->assertTrue(class_exists(MailboxAccess::class), "Failed to load class 'Slothsoft\Unity\MailboxAccess'!");
    }

    public function testRetrieveLatestCode() {
        if ($this->tryPrepareEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW)) {
            $expected = '177824';
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertEquals($expected, $sut->retrieveLatestBy('no-reply@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }

    public function testRetrieveNoCodeByEmail() {
        if ($this->tryPrepareEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }

    public function testRetrieveNoCodeByNow() {
        if ($this->tryPrepareEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT600M'), '/\b(\d{6})\b/'));
        }
    }

    public function testRetrieveNoCodeByPattern() {
        if ($this->tryPrepareEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT1000M'), '/\b(\d{7})\b/'));
        }
    }
}