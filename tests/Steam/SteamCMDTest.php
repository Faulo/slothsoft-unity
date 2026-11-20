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
        $code = 'Z7T4Q';
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2) . '/test-files/Command/steam-report-error.php'], null, [
            SteamCMD::STEAM_CREDENTIALS_PSW => false,
            'TEST_STEAM_PASSWORD' => $password,
            'TEST_STEAM_GUARD_CODE' => $code
        ]);
        $process->run();
        
        $this->assertSame(0, $process->getExitCode());
        $lines = explode(PHP_EOL, $process->getErrorOutput(), 2);
        $this->assertSame(2, substr_count($lines[0], '[REDACTED]'));
        $this->assertStringNotContainsString('secret', $lines[0]);
        $this->assertStringNotContainsString($code, $lines[0]);
        $this->assertStringContainsString($password, $lines[1]);
        $this->assertStringContainsString($code, $lines[1]);
    }
    
    /** @return iterable<string, array{string, bool}> */
    public static function timeoutCases(): iterable {
        yield 'library initial login' => ['api', false];
        yield 'library Steam Guard retry' => ['api', true];
        yield 'CLI initial login' => ['cli', false];
        yield 'CLI Steam Guard retry' => ['cli', true];
    }
    
    /** @dataProvider timeoutCases */
    public function testTimeoutRedactsSecretsWithoutChangingLoginArguments(string $entryPoint, bool $withGuard): void {
        $password = 'synthetic "steam" password';
        $code = 'Z7T4Q';
        $process = new Process([
            PHP_BINARY,
            '-d',
            'display_errors=stderr',
            '-d',
            'log_errors=0',
            '-d',
            'zend.exception_ignore_args=0',
            dirname(__DIR__, 2) . '/test-files/Command/steam-login-timeout.php',
            $entryPoint
        ], null, [
            SteamCMD::STEAM_CREDENTIALS_USR => 'test-user',
            SteamCMD::STEAM_CREDENTIALS_PSW => $entryPoint === 'cli' ? $password : false,
            'TEST_STEAM_PASSWORD' => $password,
            'TEST_STEAM_GUARD_CODE' => $withGuard ? $code : false
        ]);
        $process->run();
        
        $stderr = $process->getErrorOutput();
        $this->assertSame($entryPoint === 'cli' ? 255 : 1, $process->getExitCode());
        $this->assertStringNotContainsString('synthetic', $stderr);
        $this->assertStringNotContainsString($code, $stderr);
        $this->assertStringNotContainsString('ProcessTimedOutException', $stderr);
        $this->assertStringContainsString('SteamCMD login exceeded the timeout of 7.5 seconds.', $stderr);
        
        $expected = [['steamcmd', '+login', 'test-user', $password, '+quit']];
        if ($withGuard) {
            $expected[] = ['steamcmd', '+login', 'test-user', $password, $code, '+quit'];
        }
        $arguments = array_map(static fn(string $line): mixed => json_decode($line, true), explode("\n", trim($process->getOutput())));
        $this->assertSame($expected, $arguments);
    }
    
    /**
     * @group external
     */
    public function testLoginAnonymous(): void {
        if (! FileSystem::commandExists('steamcmd')) {
            $this->markTestSkipped('steamcmd is not available from the command line!');
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
        }
        
        $env = new TestEnvironment(SteamCMD::STEAM_CREDENTIALS_USR, SteamCMD::STEAM_CREDENTIALS_PSW, MailboxAccess::ENV_EMAIL_USR, MailboxAccess::ENV_EMAIL_PSW);
        if ($env->prepareVariables($this)) {
            $steam = new SteamCMD();
            
            $steam->mailbox = new MailboxAccess(TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_USR), TestEnvironment::requireVariable(MailboxAccess::ENV_EMAIL_PSW));
            
            $isLoggedIn = $steam->login(TestEnvironment::requireVariable(SteamCMD::STEAM_CREDENTIALS_USR), TestEnvironment::requireVariable(SteamCMD::STEAM_CREDENTIALS_PSW));
            
            $this->assertTrue($isLoggedIn);
        }
    }
}
