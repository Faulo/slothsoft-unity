<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Describes one unity-command operation independently of its result document.
 */
final readonly class OperationMetadata {
    
    private string $name;
    
    private string $className;
    
    private string $packageName;
    
    private DateTimeImmutable $startedAt;
    
    private float $duration;
    
    private string $standardOutput;
    
    private string $standardError;
    
    /**
     * @var list<string>
     */
    private array $warnings;
    
    private ?int $exitCode;
    
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        string $name,
        string $className = 'unity-command',
        string $packageName = 'slothsoft.unity',
        ?DateTimeInterface $startedAt = null,
        float $duration = 0.0,
        string $standardOutput = '',
        string $standardError = '',
        array $warnings = [],
        ?int $exitCode = null
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('The operation name must not be empty.');
        }
        if (trim($className) === '') {
            throw new InvalidArgumentException('The operation class name must not be empty.');
        }
        if (trim($packageName) === '') {
            throw new InvalidArgumentException('The operation package name must not be empty.');
        }
        if (! is_finite($duration) || $duration < 0) {
            throw new InvalidArgumentException('The operation duration must be a finite, non-negative number.');
        }
        foreach ($warnings as $warning) {
            if (! is_string($warning)) {
                throw new InvalidArgumentException('Every operation warning must be a string.');
            }
        }
        
        $this->name = $name;
        $this->className = $className;
        $this->packageName = $packageName;
        $this->startedAt = $startedAt ? DateTimeImmutable::createFromInterface($startedAt) : new DateTimeImmutable();
        $this->duration = $duration;
        $this->standardOutput = $standardOutput;
        $this->standardError = $standardError;
        $this->warnings = array_values($warnings);
        $this->exitCode = $exitCode;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function getClassName(): string {
        return $this->className;
    }
    
    public function getPackageName(): string {
        return $this->packageName;
    }
    
    public function getStartedAt(): DateTimeImmutable {
        return $this->startedAt;
    }
    
    public function getTimestamp(): string {
        return $this->startedAt->format('Y-m-d\TH:i:s');
    }
    
    public function getDuration(): float {
        return $this->duration;
    }
    
    public function getStandardOutput(): string {
        return $this->standardOutput;
    }
    
    public function getStandardError(): string {
        return $this->standardError;
    }
    
    /**
     * @return list<string>
     */
    public function getWarnings(): array {
        return $this->warnings;
    }
    
    public function getExitCode(): ?int {
        return $this->exitCode;
    }
}
