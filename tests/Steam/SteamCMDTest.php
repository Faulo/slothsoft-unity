<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Steam;

use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\MailboxAccess;
use Slothsoft\Unity\TestEnvironment;
use Symfony\Component\Process\Process;

/**
 * SteamCMDTest
 *
 * @see SteamCMD
 */
class SteamCMDTest extends TestCase {
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(SteamCMD::class), "Failed to load class 'Slothsoft\Unity\Steam\SteamCMD'!");
    }

    public function testFailurePrintsMaskedInvocationAndUnmodifiedProgramOutput(): void {
        $password = 'steam "secret" value';
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/test-files/Command/steam-report-error.php'], null, [SteamCMD::STEAM_CREDENTIALS_PSW => $password]);
        $process->run();

        $this->assertSame(0, $process->getExitCode());
        $lines = explode(PHP_EOL, $process->getErrorOutput(), 2);
        $this->assertStringContainsString('[REDACTED]', $lines[0]);
        $this->assertStringNotContainsString($password, $lines[0]);
        $this->assertStringContainsString($password, $lines[1]);
    }
    
    /**
     * @group external
     */
    public function testLoginAnonymous(): void {
        if (! FileSystem::commandExists('steamcmd')) {
            $this->markTestSkipped('steamcmd is not available from the command line!');
            return;
        }
        
        $steam = new SteamCMD();
        
        $isLoggedIn = $steam->login('anonymous');
        
        $this->assertTrue($isLoggedIn);
    }
    
    /**
     *
     * @group external
     * @runInSeparateProcess
     */
    public function testLoginViaEnv(): void {
        if (! FileSystem::commandExists('steamcmd')) {
            $this->markTestSkipped('steamcmd is not available from the command line!');
            return;
        }
        
        $env = new TestEnvironment(SteamCMD::STEAM_CREDENTIALS_USR, SteamCMD::STEAM_CREDENTIALS_PSW, MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $steam = new SteamCMD();
            
            $steam->mailbox = new MailboxAccess(getenv(MailboxAccess::ENV_EMAIL_USR), getenv(MailboxAccess::ENV_EMAIL_PSW));
            
            $isLoggedIn = $steam->login(getenv(SteamCMD::STEAM_CREDENTIALS_USR), getenv(SteamCMD::STEAM_CREDENTIALS_PSW));
            
            $this->assertTrue($isLoggedIn);
        }
    }
}
