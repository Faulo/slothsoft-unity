<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Unity\ExecutionError;
use Slothsoft\Unity\UnityHub;
use DOMDocument;
use DOMElement;
use DateTime;

/**
 * Builds process result XML for executable asset runs.
 *
 * @author Daniel Schulz
 * @since 2022-08-15
 */
final class TestResult {
    
    private string $packageName;
    
    private string $testName;
    
    private float $startTime = 0;
    
    private float $stopTime = 0;
    
    private ?ExecutionError $error = null;
    
    public function __construct(string $packageName, string $testName) {
        $this->packageName = $packageName;
        $this->testName = $testName;
        $this->startTime = microtime(true);
    }
    
    public function setError(ExecutionError $error): void {
        if (UnityHub::getThrowOnFailure()) {
            throw $error;
        }
        $this->error = $error;
    }
    
    public function asNode(DOMDocument $document): DOMElement {
        $this->stopTime = microtime(true);
        
        $rootNode = $document->createElement('result');
        
        $node = $document->createElement('process');
        $node->setAttribute('package', $this->packageName);
        $node->setAttribute('name', $this->testName);
        $node->setAttribute('start-time', date(DateTime::W3C, (int) $this->startTime));
        $node->setAttribute('duration', sprintf('%0.06f', $this->stopTime - $this->startTime));
        
        if ($this->error) {
            $node->setAttribute('stdout', $this->error->getStdOut());
            $node->setAttribute('stderr', $this->error->getStdErr());
            $node->appendChild($this->error->asNode($document));
        }
        
        $rootNode->appendChild($node);
        
        return $rootNode;
    }
}
