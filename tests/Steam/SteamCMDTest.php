<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Steam;

use PHPUnit\Framework\TestCase;
use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\MailboxAccess;
use Slothsoft\Unity\TestEnvironment;

/**
 * SteamCMDTest
 *
 * @see SteamCMD
 */
class SteamCMDTest extends TestCase {
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(SteamCMD::class), "Failed to load class 'Slothsoft\Unity\Steam\SteamCMD'!");
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
    
    /**
     *
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