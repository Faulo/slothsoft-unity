<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;
use PHPUnit\Framework\TestCase;

final class JUnitReportValidatorTest extends TestCase {
    
    public function testRejectsInvalidJUnitWithSchemaDiagnostics(): void {
        $document = new DOMDocument();
        $document->loadXML('<testsuites><testsuite /></testsuites>');
        
        try {
            (new JUnitReportValidator())->assertValid($document);
            $this->fail('Invalid JUnit must fail schema validation.');
        } catch (ReportValidationException $error) {
            $this->assertStringContainsString(JUnitReportValidator::SCHEMA_URI, $error->getMessage());
            $this->assertStringContainsString('line ', $error->getMessage());
        }
    }
}
