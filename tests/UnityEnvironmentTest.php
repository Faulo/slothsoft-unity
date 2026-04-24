<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\IsFalse;
use PHPUnit\Framework\Constraint\IsTrue;

/**
 * UnityEnvironmentTest
 *
 * @see UnityEnvironment
 */
final class UnityEnvironmentTest extends TestCase {
    
    public function testClassExists(): void {
        $this->assertTrue(class_exists(UnityEnvironment::class), "Failed to load class 'Slothsoft\Unity\UnityEnvironment'!");
    }
    
    /**
     */
    public function testCanHandleWhitespace() {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=  stdout   licensor  cache  ');
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingInput(), new IsFalse());
        $this->assertThat(UnityEnvironment::isLoggingOutput(), new IsTrue());
        $this->assertThat(UnityEnvironment::isLoggingError(), new IsFalse());
        $this->assertThat(UnityEnvironment::isLoggingLicense(), new IsTrue());
        $this->assertThat(UnityEnvironment::isLoggingCache(), new IsTrue());
    }
    
    /**
     */
    public function testDefaultLogging() {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingInput(), new IsTrue());
        $this->assertThat(UnityEnvironment::isLoggingOutput(), new IsFalse());
        $this->assertThat(UnityEnvironment::isLoggingError(), new IsTrue());
        $this->assertThat(UnityEnvironment::isLoggingLicense(), new IsFalse());
        $this->assertThat(UnityEnvironment::isLoggingCache(), new IsFalse());
    }
    
    /**
     *
     * @dataProvider isLoggingInputProvider
     */
    public function testIsLoggingInput(string $value, bool $expected) {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=' . $value);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingInput(), new IsEqual($expected));
    }
    
    public function isLoggingInputProvider(): iterable {
        yield '-' => [
            '',
            false
        ];
        
        yield UnityEnvironment::UNITY_LOG_ALL => [
            UnityEnvironment::UNITY_LOG_ALL,
            true
        ];
        
        yield UnityEnvironment::UNITY_LOG_STDIN => [
            UnityEnvironment::UNITY_LOG_STDIN,
            true
        ];
    }
    
    /**
     *
     * @dataProvider isLoggingOutputProvider
     */
    public function testIsLoggingOutput(string $value, bool $expected) {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=' . $value);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingOutput(), new IsEqual($expected));
    }
    
    public function isLoggingOutputProvider(): iterable {
        yield '-' => [
            '',
            false
        ];
        
        yield UnityEnvironment::UNITY_LOG_ALL => [
            UnityEnvironment::UNITY_LOG_ALL,
            true
        ];
        
        yield UnityEnvironment::UNITY_LOG_STDOUT => [
            UnityEnvironment::UNITY_LOG_STDOUT,
            true
        ];
    }
    
    /**
     *
     * @dataProvider isLoggingErrorProvider
     */
    public function testIsLoggingError(string $value, bool $expected) {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=' . $value);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingError(), new IsEqual($expected));
    }
    
    public function isLoggingErrorProvider(): iterable {
        yield '-' => [
            '',
            false
        ];
        
        yield UnityEnvironment::UNITY_LOG_ALL => [
            UnityEnvironment::UNITY_LOG_ALL,
            true
        ];
        
        yield UnityEnvironment::UNITY_LOG_STDERR => [
            UnityEnvironment::UNITY_LOG_STDERR,
            true
        ];
    }
    
    /**
     *
     * @dataProvider isLoggingLicenseProvider
     */
    public function testIsLoggingLicense(string $value, bool $expected) {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=' . $value);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingLicense(), new IsEqual($expected));
    }
    
    public function isLoggingLicenseProvider(): iterable {
        yield '-' => [
            '',
            false
        ];
        
        yield UnityEnvironment::UNITY_LOG_ALL => [
            UnityEnvironment::UNITY_LOG_ALL,
            true
        ];
        
        yield UnityEnvironment::UNITY_LOG_LICENSE => [
            UnityEnvironment::UNITY_LOG_LICENSE,
            true
        ];
    }
    
    /**
     *
     * @dataProvider isLoggingCacheProvider
     */
    public function testIsLoggingCache(string $value, bool $expected) {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING . '=' . $value);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingCache(), new IsEqual($expected));
    }
    
    public function isLoggingCacheProvider(): iterable {
        yield '-' => [
            '',
            false
        ];
        
        yield UnityEnvironment::UNITY_LOG_ALL => [
            UnityEnvironment::UNITY_LOG_ALL,
            true
        ];
        
        yield UnityEnvironment::UNITY_LOG_CACHE => [
            UnityEnvironment::UNITY_LOG_CACHE,
            true
        ];
    }
}