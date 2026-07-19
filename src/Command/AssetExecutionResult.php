<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Command;

use DOMDocument;
use Throwable;

final readonly class AssetExecutionResult {
    
    public function __construct(
        private int $exitCode,
        private ?DOMDocument $document = null,
        private ?Throwable $error = null
    ) {}
    
    public function getExitCode(): int {
        return $this->exitCode;
    }
    
    public function getDocument(): ?DOMDocument {
        return $this->document;
    }
    
    public function getError(): ?Throwable {
        return $this->error;
    }
}
