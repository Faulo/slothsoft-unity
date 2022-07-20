<?php
namespace Slothsoft\Unity\Assets\Project;

use DOMDocument;
use DOMElement;

class ExecutionError {

    public static function Failure(string $type, string $message): ExecutionError {
        return new self('failure', $type, $message);
    }

    public static function Error(string $type, string $message): ExecutionError {
        return new self('error', $type, $message);
    }

    /** @var string */
    private string $tag;

    /** @var string */
    private string $type;

    /** @var string */
    private string $message;

    private function __construct(string $tag, string $type, string $message) {
        $this->tag = $tag;
        $this->type = $type;
        $this->message = $message;
    }

    public function asNode(DOMDocument $document): DOMElement {
        $node = $document->createElement($this->tag);
        $node->setAttribute('type', $this->type);
        $node->textContent = $this->message;
        return $node;
    }
}

