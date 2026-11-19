<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

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
    public function testRetrieveLatestCode(): void {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW, 'EMAIL_TEST_TIME', 'EMAIL_TEST_CODE');
        if ($env->prepareVariables($this)) {
            $time = DateTimeImmutable::createFromFormat('U', TestEnvironment::requireVariable('EMAIL_TEST_TIME'));
            if ($time === false) {
                $this->fail('EMAIL_TEST_TIME must be a Unix timestamp.');
            }
            $code = TestEnvironment::requireVariable('EMAIL_TEST_CODE');
            $sut = new MailboxAccess(TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_USR), TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertEquals($code, $sut->retrieveLatestBy('no-reply@unity3d.com', $time, new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }
    
    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByEmail(): void {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_USR), TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', new DateTimeImmutable('@1745158411'), new DateInterval('PT1000M'), '/\b(\d{6})\b/'));
        }
    }
    
    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByNow(): void {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_USR), TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', new DateTimeImmutable('@1745158411'), new DateInterval('PT600M'), '/\b(\d{6})\b/'));
        }
    }
    
    /**
     *
     * @runInSeparateProcess
     */
    public function testRetrieveNoCodeByPattern(): void {
        $env = new TestEnvironment(MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $sut = new MailboxAccess(TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_USR), TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_PSW));
            $this->assertNull($sut->retrieveLatestBy('no-exist@unity3d.com', new DateTimeImmutable('@1745158411'), new DateInterval('PT1000M'), '/\b(\d{7})\b/'));
        }
    }
}