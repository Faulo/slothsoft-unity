<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use DOMDocument;
use DOMElement;
use DOMNameSpaceNode;
use DOMNode;
use DOMNodeList;
use DOMXPath;
use PHPUnit\Framework\Assert;

final class Fixture {
    public static function read(string $path): string {
        $contents = file_get_contents($path);
        if ($contents === false) {
            Assert::fail("Unable to read fixture '$path'.");
        }
        return $contents;
    }

    /** @return DOMNodeList<DOMNode|DOMNameSpaceNode> */
    public static function query(DOMXPath $xpath, string $expression): DOMNodeList {
        $nodes = $xpath->query($expression);
        if ($nodes === false) {
            Assert::fail("Invalid XPath query '$expression'.");
        }
        return $nodes;
    }

    public static function element(mixed $node): DOMElement {
        if (! $node instanceof DOMElement) {
            Assert::fail('Expected a DOM element in the fixture.');
        }
        return $node;
    }

    public static function root(DOMDocument $document): DOMElement {
        return self::element($document->documentElement);
    }
}
