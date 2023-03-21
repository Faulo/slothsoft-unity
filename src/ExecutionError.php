<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;
use DOMDocument;
use DOMElement;
use Exception;
use Throwable;

class ExecutionError extends Exception {

    public static function Failure(string $type, string $message, ?Process $process = null): ExecutionError {
        return self::FromProcess('failure', $type, $message, $process);
    }

    public static function Error(string $type, string $message, ?Process $process = null): ExecutionError {
        return self::FromProcess('error', $type, $message, $process);
    }

    private static function FromProcess(string $tag, string $type, string $message, ?Process $process): ExecutionError {
        return $process ? new self($tag, $type, $message, $process->getCommandLine(), $process->getOutput(), $process->getErrorOutput(), $process->getExitCode()) : new self($tag, $type, $message);
    }

    public static function Exception(Throwable $e, ?Process $process = null): ExecutionError {
        return $process ? new self('error', get_class($e), $e->getMessage(), $e->getTraceAsString(), $process->getOutput(), $process->getErrorOutput(), $process->getExitCode()) : new self('error', get_class($e), $e->getMessage(), $e->getTraceAsString(), '', (string) $e);
    }

    /** @var string */
    private string $tag;

    /** @var string */
    private string $type;

    /** @var string */
    private string $stdout;

    /** @var string */
    private string $stderr;

    /** @var int */
    private int $exitCode;

    /** @var string */
    private string $stackTrace;

    private function __construct(string $tag, string $type, string $message, string $stackTrace = '', string $stdout = '', string $stderr = '', int $exitCode = - 1) {
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

