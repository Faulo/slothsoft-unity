<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use InvalidArgumentException;
use Throwable;

/**
 * Canonical error data used when no command-specific result document exists.
 */
final readonly class ReportError {
    
    public const KIND_ERROR = 'error';
    
    public const KIND_FAILURE = 'failure';
    
    private string $kind;
    
    private string $type;
    
    private string $message;
    
    private string $details;
    
    public function __construct(string $kind, string $type, string $message, string $details = '') {
        if (! in_array($kind, [
            self::KIND_ERROR,
            self::KIND_FAILURE
        ], true)) {
            throw new InvalidArgumentException(sprintf("Unsupported report error kind '%s'.", $kind));
        }
        if (trim($type) === '') {
            throw new InvalidArgumentException('The report error type must not be empty.');
        }
        
        $this->kind = $kind;
        $this->type = $type;
        $this->message = $message;
        $this->details = $details;
    }
    
    public static function error(string $type, string $message, string $details = ''): self {
        return new self(self::KIND_ERROR, $type, $message, $details);
    }
    
    public static function failure(string $type, string $message, string $details = ''): self {
        return new self(self::KIND_FAILURE, $type, $message, $details);
    }
    
    public static function fromThrowable(Throwable $error, string $kind = self::KIND_ERROR): self {
        return new self($kind, get_class($error), $error->getMessage(), $error->getTraceAsString());
    }
    
    public function getKind(): string {
        return $this->kind;
    }
    
    public function getType(): string {
        return $this->type;
    }
    
    public function getMessage(): string {
        return $this->message;
    }
    
    public function getDetails(): string {
        return $this->details;
    }
}
