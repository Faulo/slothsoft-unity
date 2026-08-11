<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DateTimeImmutable;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class JUnitReporterTest extends TestCase {
    
    private JUnitReporter $reporter;
    
    protected function setUp(): void {
        $this->reporter = new JUnitReporter();
    }
    
    public function testTransformsCommandSuccessAndWarningsWithoutExposingSourceXml(): void {
        $source = $this->loadXml('<command-success><warning>Source reported a warning.</warning><internal-result>secret</internal-result></command-success>');
        $sourceBefore = $source->saveXML();
        $metadata = new OperationMetadata('build', 'Unity.Build', 'slothsoft.unity', new DateTimeImmutable('2026-08-10T10:20:30Z'), 1.5, "build output\n", "build error\n", [
            'Package cache was stale.',
            'A fallback editor was selected.'
        ], 0);
        
        $report = $this->reporter->createReport($source, $metadata);
        $xpath = new DOMXPath($report);
        
        $this->assertSame($sourceBefore, $source->saveXML(), 'Reporting must not mutate the cached Farah result.');
        $this->assertSame('1', $xpath->evaluate('string(/testsuites/testsuite/@tests)'));
        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite/@failures)'));
        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite/@errors)'));
        $this->assertSame('build', $xpath->evaluate('string(/testsuites/testsuite/@name)'));
        $this->assertSame('Unity.Build', $xpath->evaluate('string(/testsuites/testsuite/testcase/@classname)'));
        $this->assertSame('command-success', $xpath->evaluate('string(/testsuites/testsuite/properties/property[@name="unity-command.source-root"]/@value)'));
        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite/properties/property[@name="unity-command.exit-code"]/@value)'));
        $this->assertSame(3, $xpath->query('/testsuites/testsuite/properties/property[starts-with(@name, "unity-command.warning.")]')->length);
        $this->assertSame("build output\n", $xpath->evaluate('string(/testsuites/testsuite/system-out)'));
        $this->assertSame("build error\nPackage cache was stale.\nA fallback editor was selected.\nSource reported a warning.", str_replace('WARNING: ', '', $xpath->evaluate('string(/testsuites/testsuite/system-err)')));
        $this->assertStringNotContainsString('secret', $this->reporter->toXml($report));
    }
    
    public function testTransformsProcessStyleResultsAndPreservesStreamsAndStatuses(): void {
        $source = $this->loadXml(<<<'XML'
        <result>
          <process package="Unity.Build" name="Build" result="0" stdout="built" stderr="" duration="0.5" start-time="2026-08-10T10:00:00Z" />
          <process package="Unity.Tests" name="Skipped" result="0" stdout="" stderr="skip log" duration="0.1" start-time="2026-08-10T10:00:01Z"><skipped message="not applicable">skip details</skipped></process>
          <process package="Unity.Tests" name="Failed" result="1" stdout="test log" stderr="" duration="0.2" start-time="2026-08-10T10:00:02Z"><failure type="Assertion" message="expected true">failure trace</failure></process>
          <process package="Unity.Editor" name="Errored" result="17" stdout="" stderr="editor log" duration="0.3" start-time="2026-08-10T10:00:03Z"><error type="EditorCrash" message="crashed">error trace</error></process>
        </result>
        XML);
        
        $report = $this->reporter->createReport($source, $this->metadata('build'));
        $xpath = new DOMXPath($report);
        
        $this->assertSame(4, $xpath->query('/testsuites/testsuite')->length);
        $this->assertSame(4, $xpath->query('//testcase')->length);
        $this->assertSame(1, $xpath->query('//testcase/skipped')->length);
        $this->assertSame(1, $xpath->query('//testcase/failure')->length);
        $this->assertSame(1, $xpath->query('//testcase/error')->length);
        $this->assertSame('built', $xpath->evaluate('string(/testsuites/testsuite[1]/system-out)'));
        $this->assertSame('editor log', $xpath->evaluate('string(/testsuites/testsuite[4]/system-err)'));
        $this->assertSame('17', $xpath->evaluate('string(/testsuites/testsuite[4]/properties/property[@name="unity-process.exit-code"]/@value)'));
    }
    
    /**
     * @dataProvider canonicalErrorProvider
     */
    public function testCreatesCanonicalErrorReports(ReportError $error, string $element): void {
        $metadata = new OperationMetadata('package-install', 'Unity.PackageInstall', 'slothsoft.unity', new DateTimeImmutable('2026-08-10T10:20:30Z'), 0.25, 'partial output', 'diagnostic', [], 1);
        
        $report = $this->reporter->createErrorReport($metadata, $error);
        $xpath = new DOMXPath($report);
        
        $this->assertSame(1, $xpath->query('/testsuites/testsuite/testcase/' . $element)->length);
        $this->assertSame($error->getType(), $xpath->evaluate('string(/testsuites/testsuite/testcase/' . $element . '/@type)'));
        $this->assertSame($error->getMessage(), $xpath->evaluate('string(/testsuites/testsuite/testcase/' . $element . '/@message)'));
        $this->assertSame($error->getDetails(), $xpath->evaluate('string(/testsuites/testsuite/testcase/' . $element . ')'));
        $this->assertSame('partial output', $xpath->evaluate('string(/testsuites/testsuite/system-out)'));
        $this->assertSame('diagnostic', $xpath->evaluate('string(/testsuites/testsuite/system-err)'));
    }
    
    public function canonicalErrorProvider(): iterable {
        yield 'execution error' => [
            ReportError::error('RuntimeException', 'Unity was unavailable.', 'exception trace'),
            'error'
        ];
        yield 'semantic failure' => [
            ReportError::failure('TestFailure', 'Unity tests failed.', 'assertion trace'),
            'failure'
        ];
    }
    
    public function testUnityTestRunnerCasesAndStatusesArePreservedOneToOne(): void {
        $source = $this->loadXml(<<<'XML'
        <test-run start-time="2026-08-10T10:00:00Z" testcasecount="6" total="6" passed="2" failed="2" inconclusive="1" skipped="1">
          <test-suite type="TestFixture" id="1" name="Fixture" fullname="Example.Fixture" classname="Example.Fixture" start-time="2026-08-10T10:00:00Z" duration="1.5">
            <properties><property name="platform" value="EditMode" /></properties>
            <test-case id="1" name="Passes" classname="Example.Fixture" result="Passed" duration="0.1"><output>pass output</output></test-case>
            <test-case id="2" name="Fails" classname="Example.Fixture" result="Failed" duration="0.2"><failure><message>assertion failed</message><stack-trace>failure trace</stack-trace></failure></test-case>
            <test-case id="3" name="Errors" classname="Example.Fixture" result="Failed" label="Error" duration="0.3"><failure><message>unexpected error</message><stack-trace>error trace</stack-trace></failure></test-case>
            <test-case id="4" name="Skips" classname="Example.Fixture" result="Skipped" label="Ignored" duration="0"><reason><message>disabled</message><stack-trace>skip trace</stack-trace></reason></test-case>
            <test-case id="5" name="Inconclusive" classname="Example.Fixture" result="Inconclusive" duration="0.4"><reason><message>no result</message><stack-trace>inconclusive trace</stack-trace></reason></test-case>
            <test-suite type="ParameterizedMethod" id="2" name="Nested" fullname="Example.Fixture.Nested" classname="Example.Fixture" start-time="2026-08-10T10:00:01Z" duration="0.5">
              <properties />
              <test-case id="6" name="NestedPass" classname="Example.Fixture" result="Passed" duration="0.5" />
            </test-suite>
          </test-suite>
        </test-run>
        XML);
        
        $report = $this->reporter->createReport($source, $this->metadata('tests'));
        $xpath = new DOMXPath($report);
        
        $this->assertSame(2, $xpath->query('/testsuites/testsuite')->length);
        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite[1]/@id)'));
        $this->assertSame('1', $xpath->evaluate('string(/testsuites/testsuite[2]/@id)'));
        $this->assertSame(6, $xpath->query('//testcase')->length);
        $this->assertSame(1, $xpath->query('//testcase/failure')->length);
        $this->assertSame(2, $xpath->query('//testcase/error')->length);
        $this->assertSame(1, $xpath->query('//testcase/skipped')->length);
        $this->assertSame('1', $xpath->evaluate('string(/testsuites/testsuite[1]/@failures)'));
        $this->assertSame('2', $xpath->evaluate('string(/testsuites/testsuite[1]/@errors)'));
        $this->assertSame('1', $xpath->evaluate('string(/testsuites/testsuite[1]/@skipped)'));
        $this->assertSame('assertion failed', $xpath->evaluate('string(//testcase[@name="Fails"]/failure/@message)'));
        $this->assertSame('failure trace', $xpath->evaluate('string(//testcase[@name="Fails"]/failure)'));
        $this->assertSame('unexpected error', $xpath->evaluate('string(//testcase[@name="Errors"]/error/@message)'));
        $this->assertSame('disabled', $xpath->evaluate('string(//testcase[@name="Skips"]/skipped/@message)'));
        $this->assertSame('no result', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/error/@message)'));
        $this->assertSame('EditMode', $xpath->evaluate('string(/testsuites/testsuite[1]/properties/property[@name="platform"]/@value)'));
        $this->assertSame('pass output', $xpath->evaluate('string(/testsuites/testsuite[1]/system-out)'));
    }
    
    public function testUnityProcessExitCodeAddsAnErrorWithoutReplacingUnityCases(): void {
        $source = $this->loadXml(<<<'XML'
        <test-run unity-exit-code="42" start-time="2026-08-10T10:00:00Z" failed="0" inconclusive="0">
          <test-suite name="Fixture" classname="Example.Fixture" start-time="2026-08-10T10:00:00Z" duration="0.2">
            <test-case name="Passes" classname="Example.Fixture" result="Passed" duration="0.2" />
          </test-suite>
        </test-run>
        XML);
        $metadata = new OperationMetadata('tests', startedAt: new DateTimeImmutable('2026-08-10T10:00:00Z'), duration: 0.2, exitCode: 42);
        
        $report = $this->reporter->createReport($source, $metadata);
        $xpath = new DOMXPath($report);
        
        $this->assertSame(2, $xpath->query('/testsuites/testsuite')->length);
        $this->assertSame(1, $xpath->query('//testcase[@name="Passes"]')->length);
        $this->assertSame(1, $xpath->query('//testcase[@name="Unity Test Runner process"]/error')->length);
        $this->assertSame('42', $xpath->evaluate('string(/testsuites/testsuite[2]/properties/property[@name="unity-process.exit-code"]/@value)'));
    }
    
    public function testCreateXmlReturnsCompleteUtf8DocumentWithoutWriting(): void {
        $directory = temp_dir(str_replace(':', '-', __METHOD__));
        $before = scandir($directory);
        
        $xml = $this->reporter->createXml($this->loadXml('<success />'), $this->metadata('empty-project'));
        
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertSame($before, scandir($directory));
        $parsed = $this->loadXml($xml);
        (new JUnitReportValidator())->assertValid($parsed);
    }
    
    private function metadata(string $name): OperationMetadata {
        return new OperationMetadata($name, 'unity-command.' . $name, 'slothsoft.unity', new DateTimeImmutable('2026-08-10T10:20:30Z'));
    }
    
    private function loadXml(string $xml): DOMDocument {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        return $document;
    }
}
