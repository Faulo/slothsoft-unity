<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\PackageInstallation;

use PHPUnit\Framework\TestCase;

final class PackageMetadataReaderTest extends TestCase {
    private PackageMetadataReader $reader;
    private string $directory;

    protected function setUp(): void {
        $this->reader = new PackageMetadataReader();
        $this->directory = temp_dir(str_replace(':', '-', __METHOD__));
    }

    public function testReadsValidatedNameAndCanonicalPath(): void {
        $path = $this->directory . DIRECTORY_SEPARATOR . 'source';
        mkdir($path);
        file_put_contents($path . DIRECTORY_SEPARATOR . 'package.json', '{"name":"com.example.package"}');

        $actual = $this->reader->read($path);

        $this->assertSame(realpath($path), $actual->getPath());
        $this->assertSame('com.example.package', $actual->getName());
    }

    public function testRejectsMissingPackageJson(): void {
        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('has no package.json');

        $this->reader->read($this->directory);
    }

    public function testRejectsInvalidPackageJson(): void {
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . 'package.json', '{');

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('Unable to parse package metadata');

        $this->reader->read($this->directory);
    }

    /**
     * @dataProvider invalidNameProvider
     */
    public function testRejectsUnsafePackageName(mixed $name): void {
        file_put_contents($this->directory . DIRECTORY_SEPARATOR . 'package.json', json_encode([
            'name' => $name
        ], JSON_THROW_ON_ERROR));

        $this->expectException(PackageInstallationException::class);
        $this->expectExceptionMessage('has an invalid package name');

        $this->reader->read($this->directory);
    }

    public function invalidNameProvider(): iterable {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'parent traversal' => ['..'];
        yield 'forward slash' => ['com.example/escape'];
        yield 'backslash' => ['com.example\\escape'];
        yield 'drive separator' => ['C:escape'];
        yield 'leading dot' => ['.hidden'];
        yield 'trailing dot' => ['com.example.'];
        yield 'windows device name' => ['CON'];
        yield 'windows device name with extension' => ['lpt1.package'];
        yield 'non-string' => [123];
    }
}
