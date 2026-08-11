<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use PHPUnit\Framework\TestCase;

final class ManifestFileManagerTest extends TestCase {
    private ManifestFileManager $manager;
    private string $directory;

    protected function setUp(): void {
        $this->manager = new ManifestFileManager();
        $this->directory = temp_dir(str_replace(':', '-', __METHOD__));
    }

    public function testReadsJsonObject(): void {
        $path = $this->writeJson('manifest.json', [
            'dependencies' => [
                'com.example.package' => '1.0.0'
            ]
        ]);

        $actual = $this->manager->read($path);
        $this->assertSame('1.0.0', $actual->dependencies->{'com.example.package'});
    }

    public function testRejectsInvalidJson(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'invalid.json';
        file_put_contents($path, '{');

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage("Unable to parse manifest '$path'");

        $this->manager->read($path);
    }

    public function testRejectsNonObjectJsonRoots(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'list.json';
        file_put_contents($path, '["not", "an", "object"]');

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('must contain a JSON object');

        $this->manager->read($path);
    }
    
    public function testRejectsEmptyListJsonRoot(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'empty-list.json';
        file_put_contents($path, '[]');
        
        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('must contain a JSON object');
        
        $this->manager->read($path);
    }

    public function testMergeFilesValidatesWithoutChangingExistingManifest(): void {
        $existingPath = $this->writeJson('manifest.json', [
            'dependencies' => [
                'com.example.package' => '1.0.0'
            ]
        ]);
        $incomingPath = $this->writeJson('installation.json', [
            'dependencies' => [
                'com.example.package' => '2.0.0'
            ]
        ]);
        $before = file_get_contents($existingPath);

        $merged = $this->manager->mergeFiles($existingPath, $incomingPath);

        $this->assertSame('2.0.0', $merged->dependencies->{'com.example.package'});
        $this->assertSame($before, file_get_contents($existingPath));
    }

    public function testMergeAndWriteAtomicallyReplacesManifest(): void {
        $existingPath = $this->writeJson('manifest.json', [
            'dependencies' => [
                'com.example.keep' => '1.0.0'
            ]
        ]);
        $incomingPath = $this->writeJson('installation.json', [
            'dependencies' => [
                'com.example.add' => '2.0.0'
            ]
        ]);

        $merged = $this->manager->mergeAndWrite($existingPath, $incomingPath);

        $this->assertEquals($merged, $this->manager->read($existingPath));
        $this->assertStringEndsWith("\n", file_get_contents($existingPath));
        $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.manifest-*.tmp'));
    }

    public function testInvalidIncomingManifestLeavesExistingFileByteUnchanged(): void {
        $existingPath = $this->writeJson('manifest.json', [
            'dependencies' => [
                'com.example.keep' => '1.0.0'
            ]
        ]);
        $incomingPath = $this->directory . DIRECTORY_SEPARATOR . 'installation.json';
        file_put_contents($incomingPath, '{invalid');
        $before = file_get_contents($existingPath);

        try {
            $this->manager->mergeAndWrite($existingPath, $incomingPath);
            $this->fail('Invalid installation JSON should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame($before, file_get_contents($existingPath));
            $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.manifest-*.tmp'));
        }
    }

    public function testEncodingFailureLeavesExistingFileByteUnchanged(): void {
        $path = $this->writeJson('manifest.json', [
            'preserved' => true
        ]);
        $before = file_get_contents($path);

        try {
            $this->manager->write($path, (object) [
                'invalid-number' => INF
            ]);
            $this->fail('Unencodable JSON should fail.');
        } catch (PackageInstallationException) {
            $this->assertSame($before, file_get_contents($path));
            $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.manifest-*.tmp'));
        }
    }

    public function testWriteRejectsMissingParentWithoutCreatingIt(): void {
        $parent = $this->directory . DIRECTORY_SEPARATOR . 'missing';
        $path = $parent . DIRECTORY_SEPARATOR . 'manifest.json';

        try {
            $this->manager->write($path, (object) [
                'dependencies' => (object) []
            ]);
            $this->fail('Writing below a missing parent should fail.');
        } catch (PackageInstallationException) {
            $this->assertDirectoryDoesNotExist($parent);
        }
    }

    public function testWritesEmptyManifestAndDependenciesAsJsonObjects(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';

        $this->manager->write($path, (object) [
            'dependencies' => (object) []
        ]);

        $this->assertSame("{\n    \"dependencies\": {}\n}\n", file_get_contents($path));
    }
    
    public function testRoundTripPreservesEmptyObjectsListsAndNumericObjectKeys(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';
        file_put_contents($path, '{"emptyObject":{},"emptyList":[],"numericObject":{"0":"zero","1":"one"}}');
        
        $manifest = $this->manager->read($path);
        $this->manager->write($path, $manifest);
        $document = json_decode(file_get_contents($path));
        
        $this->assertInstanceOf(\stdClass::class, $document);
        $this->assertInstanceOf(\stdClass::class, $document->emptyObject);
        $this->assertSame([], $document->emptyList);
        $this->assertInstanceOf(\stdClass::class, $document->numericObject);
        $this->assertSame('zero', $document->numericObject->{'0'});
        $this->assertSame('one', $document->numericObject->{'1'});
    }
    
    public function testIncomingEmptyObjectWinsListConflict(): void {
        $existingPath = $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';
        $incomingPath = $this->directory . DIRECTORY_SEPARATOR . 'installation.json';
        file_put_contents($existingPath, '{"settings":["old"]}');
        file_put_contents($incomingPath, '{"settings":{}}');
        
        $manifest = $this->manager->mergeAndWrite($existingPath, $incomingPath);
        $document = json_decode(file_get_contents($existingPath));
        
        $this->assertInstanceOf(\stdClass::class, $manifest->settings);
        $this->assertInstanceOf(\stdClass::class, $document->settings);
    }
    
    public function testMergeAndWritePreservesStrictIntegerAndFloatListValues(): void {
        $existingPath = $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';
        $incomingPath = $this->directory . DIRECTORY_SEPARATOR . 'installation.json';
        file_put_contents($existingPath, '{"values":[1]}');
        file_put_contents($incomingPath, '{"values":[1.0]}');
        
        $this->manager->mergeAndWrite($existingPath, $incomingPath);
        
        $this->assertStringContainsString('1.0', file_get_contents($existingPath));
        $this->assertSame([1, 1.0], json_decode(file_get_contents($existingPath), true)['values']);
    }

    public function testStagingDoesNotChangeTargetAndCanBeDiscarded(): void {
        $path = $this->writeJson('manifest.json', [
            'preserved' => true
        ]);
        $before = file_get_contents($path);

        $staged = $this->manager->stage($path, (object) [
            'replacement' => true
        ]);

        $this->assertSame($before, file_get_contents($path));
        $this->assertFileExists($staged['temporary']);
        $this->manager->discard($staged);
        $this->assertFileDoesNotExist($staged['temporary']);
        $this->assertSame($before, file_get_contents($path));
    }

    public function testRejectsManifestSymlinkBeforeStaging(): void {
        $outside = $this->writeJson('outside.json', [
            'preserved' => true
        ]);
        $path = $this->directory . DIRECTORY_SEPARATOR . 'manifest.json';
        if (! @symlink($outside, $path)) {
            $this->markTestSkipped('Symbolic links are not available on this platform.');
        }

        try {
            $this->manager->stage($path, (object) [
                'replacement' => true
            ]);
            $this->fail('A symlinked manifest should fail preflight.');
        } catch (PackageInstallationException) {
            $this->assertSame(json_encode([
                'preserved' => true
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), file_get_contents($outside));
            $this->assertSame([], glob($this->directory . DIRECTORY_SEPARATOR . '.manifest-*.tmp'));
        }
    }

    private function writeJson(string $name, array $value): string {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }
}
