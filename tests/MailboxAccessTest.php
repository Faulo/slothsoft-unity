<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

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
            foreach (Dotenv::createImmutable(getcwd(), '.env.local')->load() as $key => $value) {
                putenv("$key=$value");
            }

            if ($expected = getenv('EMAIL_CREDENTIALS_CODE')) {
                $sut = new MailboxAccess();
                $this->assertEquals($expected, $sut->retrieveLatestBy('no-reply@unity3d.com', '/\b(\d{6})\b/'));
            } else {
                $this->markTestSkipped('Missing env variable "EMAIL_CREDENTIALS_CODE" in ".env.local".');
            }
        } else {
            $this->markTestSkipped('Missing email login file ".env.local".');
        }
    }
}