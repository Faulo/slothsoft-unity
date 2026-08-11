<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class OperationMetadataTest extends TestCase {
    
    public function testStoresStableOperationData(): void {
        $startedAt = new DateTimeImmutable('2026-08-10T12:34:56+02:00');
        $metadata = new OperationMetadata('build', 'Unity.Build', 'slothsoft.unity', $startedAt, 1.25, 'stdout', 'stderr', [
            'editor warning'
        ], 17);
        
        $this->assertSame('build', $metadata->getName());
        $this->assertSame('Unity.Build', $metadata->getClassName());
        $this->assertSame('slothsoft.unity', $metadata->getPackageName());
        $this->assertSame('2026-08-10T12:34:56', $metadata->getTimestamp());
        $this->assertSame(1.25, $metadata->getDuration());
        $this->assertSame('stdout', $metadata->getStandardOutput());
        $this->assertSame('stderr', $metadata->getStandardError());
        $this->assertSame([
            'editor warning'
        ], $metadata->getWarnings());
        $this->assertSame(17, $metadata->getExitCode());
    }
    
    /**
     * @dataProvider invalidMetadataProvider
     */
    public function testRejectsInvalidMetadata(string $name, string $className, string $packageName, float $duration, array $warnings): void {
        $this->expectException(InvalidArgumentException::class);
        
        new OperationMetadata($name, $className, $packageName, null, $duration, '', '', $warnings);
    }
    
    public function invalidMetadataProvider(): iterable {
        yield 'empty operation name' => [
            '',
            'unity-command',
            'slothsoft.unity',
            0.0,
            []
        ];
        yield 'empty class name' => [
            'build',
            '',
            'slothsoft.unity',
            0.0,
            []
        ];
        yield 'empty package name' => [
            'build',
            'unity-command',
            '',
            0.0,
            []
        ];
        yield 'negative duration' => [
            'build',
            'unity-command',
            'slothsoft.unity',
            -0.1,
            []
        ];
        yield 'non-string warning' => [
            'build',
            'unity-command',
            'slothsoft.unity',
            0.0,
            [
                123
            ]
        ];
    }
}
