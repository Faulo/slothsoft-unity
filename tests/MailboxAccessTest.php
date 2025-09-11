<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use DateInterval;
use DateTimeImmutable;

/**
 * MailboxAccessTest
 *
 * @see MailboxAccess
 */
class MailboxAccessTest extends TestCase {

    public function testClassExists(): void {
        $this->assertTrue(class_exists(MailboxAccess::class), "Failed to load class 'Slothsoft\Unity\MailboxAccess'!");
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveLatestCode() {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW, 'EMAIL_TEST_TIME', 'EMAIL_TEST_CODE');
        if ($env->prepareVariables($this)) {
            $time = getenv('EMAIL_TEST_TIME');
            $code = getenv('EMAIL_TEST_CODE');
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertEquals($code, $sut->retrieveLatestBy('no-reply@unity3d.com', DateTimeImmutable::createFromFormat('U', $time), new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByEmail() {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByNow() {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT600M'), '/\b(\d{6})\b/'));
        }
    }

    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByPattern() {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', DateTimeImmutable::createFromFormat('U', '1745158411'), new DateInterval('PT1000M'), '/\b(\d{7})\b/'));
        }
    }
}