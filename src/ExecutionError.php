<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use DOMDocument;
use DOMElement;
use Exception;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Represents a Unity automation failure with process output that can be exported as JUnit-style XML.
 *
 * @author Daniel Schulz
 * @since 2022-08-15
 */
final class ExecutionError extends Exception {
    
    public static function Failure(string $type, string $message, ?Process $process = null): ExecutionError {
        return self::FromProcess('failure', $type, $message, $process);
    }
    
    public static function Error(string $type, string $message, ?Process $process = null): ExecutionError {
        return self::FromProcess('error', $type, $message, $process);
    }
    
    private static function FromProcess(string $tag, string $type, string $message, ?Process $process): ExecutionError {
        return $process ? new self($tag, $type, $message, $process->getCommandLine(), $process->getOutput(), $process->getErrorOutput(), $process->getExitCode() ?? 0) : new self($tag, $type, $message);
    }
    
    public static function Exception(Throwable $e, ?Process $process = null): ExecutionError {
        return $process ? new self('error', get_class($e), $e->getMessage(), $e->getTraceAsString(), $process->getOutput(), $process->getErrorOutput(), $process->getExitCode() ?? 0) : new self('error', get_class($e), $e->getMessage(), $e->getTraceAsString(), '', (string) $e);
    }
    
    private string $tag;
    
    private string $type;
    
    private string $stdout;
    
    private string $stderr;
    
    private int $exitCode;
    
    private string $stackTrace;
    
    private function __construct(string $tag, string $type, string $message, string $stackTrace = '', string $stdout = '', string $stderr = '', int $exitCode = 0) {
        parent::__construct($message);
        
        $this->tag = $tag;
        $this->type = $type;
        $this->stackTrace = $stackTrace;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
        $this->exitCode = $exitCode;
    }
    
    public function getStdOut(): string {
        return $this->stdout;
    }
    
    public function getStdErr(): string {
        return $this->stderr;
    }
    
    public function getExitCode(): int {
        return $this->exitCode;
    }
    
    public function asNode(DOMDocument $document): DOMElement {
        $node = $document->createElement($this->tag);
        $node->setAttribute('type', $this->type);
        $node->setAttribute('message', $this->message);
        if ($this->stackTrace !== '') {
            $node->textContent = $this->stackTrace;
        }
        return $node;
    }
}
