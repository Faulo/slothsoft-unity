<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;
use PHPUnit\Framework\TestCase;

final class JUnitReportValidatorTest extends TestCase {

    public function testAcceptsJenkinsTestcaseOutputExtensions(): void {
        $document = new DOMDocument();
        $document->loadXML(<<<'XML'
        <testsuites>
          <testsuite id="0" name="Example" tests="1" failures="0" errors="0" skipped="1" time="0" timestamp="2026-08-14T00:00:00" hostname="localhost" package="">
            <properties />
            <testcase name="Inconclusive" classname="Example" time="0">
              <skipped message="Inconclusive: unavailable" />
              <system-out>captured output</system-out>
              <system-err>diagnostic trace</system-err>
            </testcase>
            <system-out />
            <system-err />
          </testsuite>
        </testsuites>
        XML);

        (new JUnitReportValidator())->assertValid($document);
        $this->addToAssertionCount(1);
    }
    
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
