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
    
    public static function tearDownAfterClass(): void {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING);
        putenv(UnityEnvironment::ENV_UNITY_NO_GRAPHICS);
        putenv(UnityEnvironment::ENV_UNITY_ACCELERATOR_ENDPOINT);
        putenv(UnityEnvironment::ENV_UNITY_ACCELERATOR_PARAMS);
        UnityEnvironment::reload();
    }
    
    /**
     *
     * @dataProvider provideGetNoGraphics
     */
    public function testGetNoGraphics(?string $value, bool $expected): void {
        putenv(UnityEnvironment::ENV_UNITY_NO_GRAPHICS . ($value === null ? '' : "=$value"));
        
        $this->assertThat(UnityEnvironment::getNoGraphics(), new IsEqual($expected));
    }
    
    public function provideGetNoGraphics(): iterable {
        yield 'empty' => [
            '',
            false
        ];
        yield 'unset' => [
            null,
            false
        ];
        
        yield '1' => [
            '1',
            true
        ];
        
        yield '0' => [
            '0',
            false
        ];
    }
    
    /**
     *
     * @dataProvider provideGetAcceleratorEndpoint
     */
    public function testGetAcceleratorEndpoint(?string $value, ?string $expected): void {
        putenv(UnityEnvironment::ENV_UNITY_ACCELERATOR_ENDPOINT . ($value === null ? '' : "=$value"));
        
        $this->assertThat(UnityEnvironment::getAcceleratorEndpoint(), new IsEqual($expected));
    }
    
    public function provideGetAcceleratorEndpoint(): iterable {
        yield 'empty' => [
            '',
            null
        ];
        yield 'unset' => [
            null,
            null
        ];
        
        yield 'test' => [
            'test',
            'test'
        ];
    }
    
    /**
     *
     * @dataProvider provideGetAcceleratorParams
     */
    public function testGetAcceleratorParams(?string $value, array $expected): void {
        putenv(UnityEnvironment::ENV_UNITY_ACCELERATOR_PARAMS . ($value === null ? '' : "=$value"));
        
        $this->assertThat(UnityEnvironment::getAcceleratorParams(), new IsEqual($expected));
    }
    
    public function provideGetAcceleratorParams(): iterable {
        yield 'empty' => [
            '',
            []
        ];
        yield 'unset' => [
            null,
            []
        ];
        
        yield 'test' => [
            'a b',
            [
                'a',
                'b'
            ]
        ];
    }
    
    /**
     */
    public function testCanHandleWhitespace(): void {
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
    public function testDefaultLogging(): void {
        putenv(UnityEnvironment::ENV_UNITY_LOGGING);
        UnityEnvironment::reload();
        
        $this->assertThat(UnityEnvironment::isLoggingInput(), new IsFalse());
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