<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;

/**
 * High-level integration API for transforming, validating, and writing JUnit.
 */
final readonly class JUnitReporter {
    
    private JUnitReportTransformer $transformer;
    
    private JUnitReportValidator $validator;
    
    private AtomicReportWriter $writer;
    
    public function __construct(?JUnitReportTransformer $transformer = null, ?JUnitReportValidator $validator = null, ?AtomicReportWriter $writer = null) {
        $this->transformer = $transformer ?? new JUnitReportTransformer();
        $this->validator = $validator ?? new JUnitReportValidator();
        $this->writer = $writer ?? new AtomicReportWriter();
    }
    
    public function createReport(DOMDocument $source, OperationMetadata $metadata): DOMDocument {
        $report = $this->transformer->transformResult($source, $metadata);
        $this->validator->assertValid($report);
        return $report;
    }
    
    public function createErrorReport(OperationMetadata $metadata, ReportError $error): DOMDocument {
        $report = $this->transformer->transformError($metadata, $error);
        $this->validator->assertValid($report);
        return $report;
    }
    
    public function createXml(DOMDocument $source, OperationMetadata $metadata): string {
        return $this->toXml($this->createReport($source, $metadata));
    }
    
    public function createErrorXml(OperationMetadata $metadata, ReportError $error): string {
        return $this->toXml($this->createErrorReport($metadata, $error));
    }
    
    public function toXml(DOMDocument $report): string {
        $this->validator->assertValid($report);
        $report->encoding = 'UTF-8';
        $xml = $report->saveXML();
        if ($xml === false) {
            throw new ReportTransformationException('Unable to serialize the JUnit report as UTF-8 XML.');
        }
        return $xml;
    }
    
    public function write(DOMDocument $report, string $path): string {
        return $this->writer->write($path, $this->toXml($report));
    }
    
    public function createAndWrite(DOMDocument $source, OperationMetadata $metadata, string $path): string {
        return $this->write($this->createReport($source, $metadata), $path);
    }
    
    public function createErrorAndWrite(OperationMetadata $metadata, ReportError $error, string $path): string {
        return $this->write($this->createErrorReport($metadata, $error), $path);
    }
}
