<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Package;

use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Module;
use Slothsoft\Unity\UnityHub;

final class WorkspaceInstallExecutableTest extends TestCase {
    
    /**
     * @runInSeparateProcess
     */
    public function testReusesExactProjectAndReplacesPackageWithoutUnityHub(): void {
        UnityHub::setThrowOnFailure(false);
        $workspace = temp_dir(str_replace(':', '-', __METHOD__) . '-workspace');
        $package = temp_dir(str_replace(':', '-', __METHOD__) . '-package');
        $this->createProject($workspace, json_encode([
            'dependencies' => [
                'existing.package' => '1.0.0'
            ],
            'customRepository' => [
                'url' => 'https://packages.example.test'
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->createPackage($package, 'example.package', 'new payload');
        $destination = $workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'example.package';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'stale.txt', 'stale payload');
        $versionBefore = file_get_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt');
        
        $document = Module::resolveToDOMWriter($this->createUrl($workspace, $package))->toDocument();
        
        $this->assertSame(0, $document->getElementsByTagName('error')->length, $document->saveXML());
        $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'stale.txt');
        $this->assertSame('new payload', file_get_contents($destination . DIRECTORY_SEPARATOR . 'payload.txt'));
        $this->assertSame($versionBefore, file_get_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt'));
        $manifest = json_decode(file_get_contents($workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json'), true);
        $this->assertSame('1.0.0', $manifest['dependencies']['existing.package']);
        $this->assertSame('1.1.33', $manifest['dependencies']['com.unity.test-framework']);
        $this->assertSame('https://packages.example.test', $manifest['customRepository']['url']);
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testRejectsInvalidNonEmptyWorkspaceBeforeModification(): void {
        UnityHub::setThrowOnFailure(false);
        $workspace = temp_dir(str_replace(':', '-', __METHOD__) . '-workspace');
        $package = temp_dir(str_replace(':', '-', __METHOD__) . '-package');
        $sentinel = $workspace . DIRECTORY_SEPARATOR . 'sentinel.txt';
        file_put_contents($sentinel, 'keep me');
        $this->createPackage($package, 'example.package', 'new payload');
        
        $document = Module::resolveToDOMWriter($this->createUrl($workspace, $package))->toDocument();
        
        $this->assertSame(1, $document->getElementsByTagName('error')->length);
        $this->assertSame('AssertWorkspace', $document->getElementsByTagName('error')->item(0)->getAttribute('type'));
        $this->assertSame('keep me', file_get_contents($sentinel));
        $this->assertDirectoryDoesNotExist($workspace . DIRECTORY_SEPARATOR . 'Packages');
    }

    /**
     * @runInSeparateProcess
     */
    public function testManifestSymlinkFailsBeforePackageReplacement(): void {
        UnityHub::setThrowOnFailure(false);
        $workspace = temp_dir(str_replace(':', '-', __METHOD__) . '-workspace');
        $package = temp_dir(str_replace(':', '-', __METHOD__) . '-package');
        $outside = temp_dir(str_replace(':', '-', __METHOD__) . '-outside');
        $manifestContents = "{\n    \"dependencies\": {}\n}\n";
        $this->createProject($workspace, trim($manifestContents));
        $manifestPath = $workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json';
        $outsideManifest = $outside . DIRECTORY_SEPARATOR . 'manifest.json';
        file_put_contents($outsideManifest, $manifestContents);
        unlink($manifestPath);
        if (! @symlink($outsideManifest, $manifestPath)) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }
        $this->createPackage($package, 'example.package', 'new payload');
        $destination = $workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'example.package';
        mkdir($destination);
        file_put_contents($destination . DIRECTORY_SEPARATOR . 'stale.txt', 'stale payload');

        $document = Module::resolveToDOMWriter($this->createUrl($workspace, $package))->toDocument();

        $this->assertSame(1, $document->getElementsByTagName('error')->length);
        $this->assertSame('stale payload', file_get_contents($destination . DIRECTORY_SEPARATOR . 'stale.txt'));
        $this->assertFileDoesNotExist($destination . DIRECTORY_SEPARATOR . 'payload.txt');
        $this->assertSame($manifestContents, file_get_contents($outsideManifest));
    }
    
    private function createProject(string $workspace, string $manifest): void {
        mkdir($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings');
        mkdir($workspace . DIRECTORY_SEPARATOR . 'Packages');
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectVersion.txt', "m_EditorVersion: 2022.1.0f1\n");
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'ProjectSettings' . DIRECTORY_SEPARATOR . 'ProjectSettings.asset', "PlayerSettings:\n  productName: Existing Project\n");
        file_put_contents($workspace . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'manifest.json', $manifest . "\n");
    }
    
    private function createPackage(string $package, string $name, string $payload): void {
        file_put_contents($package . DIRECTORY_SEPARATOR . 'package.json', json_encode([
            'name' => $name,
            'version' => '1.0.0'
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($package . DIRECTORY_SEPARATOR . 'payload.txt', $payload);
    }
    
    private function createUrl(string $workspace, string $package): FarahUrl {
        return FarahUrl::createFromComponents('slothsoft@unity', '/package/install-workspace', FarahUrlArguments::createFromValueList([
            'workspace' => $workspace,
            'package' => $package
        ]));
    }
}
