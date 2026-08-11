<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;
use DOMElement;
use LibXMLError;
use Slothsoft\Unity\JUnit;
use Throwable;
use XSLTProcessor;

/**
 * Wraps operation data in a stable XML envelope and transforms it to JUnit.
 */
final readonly class JUnitReportTransformer {
    
    private string $stylesheetPath;
    
    public function __construct(?string $stylesheetPath = null) {
        $this->stylesheetPath = $stylesheetPath ?? dirname(__DIR__, 3) . '/assets/xsl/to-junit.xsl';
    }
    
    public function transformResult(DOMDocument $source, OperationMetadata $metadata): DOMDocument {
        if (! $source->documentElement) {
            throw new ReportTransformationException('Cannot create a JUnit report from an empty result document.');
        }
        
        return $this->transform($this->createEnvelope($metadata, $source));
    }
    
    public function transformError(OperationMetadata $metadata, ReportError $error): DOMDocument {
        return $this->transform($this->createEnvelope($metadata, null, $error));
    }
    
    private function createEnvelope(OperationMetadata $metadata, ?DOMDocument $source = null, ?ReportError $error = null): DOMDocument {
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElement('unity-command-report');
        $root->setAttribute('name', $metadata->getName());
        $root->setAttribute('classname', $metadata->getClassName());
        $root->setAttribute('package', $metadata->getPackageName());
        $root->setAttribute('timestamp', $metadata->getTimestamp());
        $root->setAttribute('duration', $this->formatDuration($metadata->getDuration()));
        if ($metadata->getExitCode() !== null) {
            $root->setAttribute('exit-code', (string) $metadata->getExitCode());
        }
        $document->appendChild($root);
        
        foreach ($metadata->getWarnings() as $warning) {
            $this->appendTextElement($document, $root, 'warning', $warning);
        }
        if ($source?->documentElement) {
            foreach ($source->documentElement->childNodes as $child) {
                if ($child instanceof DOMElement and $child->localName === 'warning') {
                    $this->appendTextElement($document, $root, 'warning', $child->textContent);
                }
            }
        }
        $this->appendTextElement($document, $root, 'standard-output', $metadata->getStandardOutput());
        $this->appendTextElement($document, $root, 'standard-error', $metadata->getStandardError());
        
        if ($error) {
            $problem = $document->createElement('problem');
            $problem->setAttribute('kind', $error->getKind());
            $problem->setAttribute('type', $error->getType());
            $problem->setAttribute('message', $error->getMessage());
            $problem->textContent = $error->getDetails();
            $root->appendChild($problem);
        }
        
        if ($source) {
            $sourceNode = $document->createElement('source');
            $sourceNode->appendChild($document->importNode($source->documentElement, true));
            $root->appendChild($sourceNode);
        }
        
        return $document;
    }
    
    private function appendTextElement(DOMDocument $document, DOMElement $parent, string $name, string $value): void {
        $element = $document->createElement($name);
        $element->textContent = $value;
        $parent->appendChild($element);
    }
    
    private function formatDuration(float $duration): string {
        $formatted = rtrim(rtrim(sprintf('%.6F', $duration), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }
    
    private function transform(DOMDocument $source): DOMDocument {
        $previousInternalErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        
        try {
            $stylesheet = new DOMDocument();
            if (! @$stylesheet->load($this->stylesheetPath, LIBXML_NONET)) {
                $errors = libxml_get_errors();
                throw new ReportTransformationException($this->formatFailure('Unable to load the JUnit stylesheet', $errors));
            }
            
            $processor = new XSLTProcessor();
            $processor->registerPHPFunctions([
                JUnit::class . '::formatDate'
            ]);
            if (! @$processor->importStylesheet($stylesheet)) {
                $errors = libxml_get_errors();
                throw new ReportTransformationException($this->formatFailure('Unable to import the JUnit stylesheet', $errors));
            }
            
            $report = @$processor->transformToDoc($source);
            if (! $report instanceof DOMDocument || ! $report->documentElement) {
                $errors = libxml_get_errors();
                throw new ReportTransformationException($this->formatFailure('Unable to transform the operation result to JUnit', $errors));
            }
            $report->encoding = 'UTF-8';
            $report->formatOutput = true;
            return $report;
        } catch (ReportTransformationException $error) {
            throw $error;
        } catch (Throwable $error) {
            $errors = libxml_get_errors();
            throw new ReportTransformationException($this->formatFailure('Unable to transform the operation result to JUnit', $errors), 0, $error);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }
    
    /**
     * @param list<LibXMLError> $errors
     */
    private function formatFailure(string $message, array $errors): string {
        if (! $errors) {
            return $message . '.';
        }
        
        $details = [];
        foreach ($errors as $error) {
            $details[] = sprintf('line %d, column %d: %s', $error->line, $error->column, trim($error->message));
        }
        return $message . ":\n" . implode("\n", $details);
    }
}
