<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\DotNet;

class FormatLog {

    public function __construct(string $path) {
        if (! realpath($path)) {
            throw new \InvalidArgumentException("Missing format-report.json '$path'!");
        }
    }

    public function asDocument(): \DOMDocument {
        return new \DOMDocument();
    }
}


