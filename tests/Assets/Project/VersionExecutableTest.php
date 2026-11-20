<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Project;

use DOMElement;
use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Unity\Command\FarahAssetResolver;
use Slothsoft\Unity\Fixture;
use Slothsoft\Unity\LocateHubFromCommand;
use Slothsoft\Unity\UnityHub;

/**
 * VersionExecutableTest
 *
 * @see VersionExecutable
 *
 */
final class VersionExecutableTest extends TestCase {
    
    /**
     *
     * @test
     */
    public function testClassExists(): void {
        $this->assertTrue(class_exists(VersionExecutable::class), "Failed to load class 'Slothsoft\Unity\Assets\Project\VersionExecutable'!");
    }
    
    /** @runInSeparateProcess */
    public function testVersionAssetReadsWritesAndRejectsUnknownModes(): void {
        $workspace = temp_dir('project-version-asset');
        $this->assertTrue(mkdir($workspace . '/ProjectSettings'));
        $source = dirname(__DIR__, 3) . '/test-files/ValidProject/ProjectSettings/';
        $this->assertTrue(copy($source . 'ProjectVersion.txt', $workspace . '/ProjectSettings/ProjectVersion.txt'));
        $this->assertTrue(copy($source . 'ProjectSettings.asset', $workspace . '/ProjectSettings/ProjectSettings.asset'));
        UnityHub::setHubLocator(new LocateHubFromCommand([PHP_BINARY]));
        UnityHub::setThrowOnFailure(false);
        
        $this->assertSame('1.3.0.55', $this->resolveVersion($workspace, 'get')->textContent);
        $this->assertSame('2.1.0', $this->resolveVersion($workspace, 'set')->textContent);
        $failure = $this->resolveVersion($workspace, 'unknown');
        $this->assertSame('result', $failure->tagName);
        $error = Fixture::element($failure->getElementsByTagName('error')->item(0));
        $this->assertSame('AssertParameter', $error->getAttribute('type'));
        $this->assertStringContainsString('bundleVersion: 2.1.0', Fixture::read($workspace . '/ProjectSettings/ProjectSettings.asset'));
    }
    
    private function resolveVersion(string $workspace, string $mode): DOMElement {
        $url = FarahUrl::createFromComponents('slothsoft@unity', '/project/version', FarahUrlArguments::createFromValueList([
            'workspace' => $workspace,
            'mode' => $mode,
            'version' => '2.1.0'
        ]));
        return Fixture::root((new FarahAssetResolver())->resolve($url));
    }
}
