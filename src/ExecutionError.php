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
        return self::FromProcess('failure', $type, $message, $process);
    }

    private static function FromProcess(string $tag, string $type, string $message, ?Process $process): ExecutionError {
        return $process ? new self($tag, $type, $message, $process->getCommandLine(), $process->getOutput(), $process->getErrorOutput()) : new self($tag, $type, $message);
    }

    public static function Exception(Throwable $e): ExecutionError {
        return new self('error', get_class($e), $e->getMessage(), $e->getTraceAsString(), 'STDOUT', 'STDERR');
    }

    /** @var string */
    private string $tag;

    /** @var string */
    private string $type;

    /** @var string */
    private string $stdout;

    /** @var string */
    private string $stderr;

    /** @var string */
    private string $stackTrace;

    private function __construct(string $tag, string $type, string $message, string $stackTrace = '', string $stdout = '', string $stderr = '') {
        parent::__construct($message);

        $this->tag = $tag;
        $this->type = $type;
        $this->stackTrace = $stackTrace;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    public function getStdOut(): string {
        return $this->stdout;
    }

    public function getStdErr(): string {
        return $this->stderr;
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

