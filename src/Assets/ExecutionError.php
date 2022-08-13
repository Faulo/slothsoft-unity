<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use DOMDocument;
use DOMElement;

class ExecutionError {

    public static function Failure(string $type, string $message, string $stackTrace = ''): ExecutionError {
        return new self('failure', $type, $message, $stackTrace);
    }

    public static function Error(string $type, string $message, string $stackTrace = ''): ExecutionError {
        return new self('error', $type, $message, $stackTrace);
    }

    /** @var string */
    private string $tag;

    /** @var string */
    private string $type;

    /** @var string */
    private string $message;

    /** @var string */
    private string $stackTrace;

    private function __construct(string $tag, string $type, string $message, string $stackTrace) {
        $this->tag = $tag;
        $this->type = $type;
        $this->message = $message;
        $this->stackTrace = $stackTrace;
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

