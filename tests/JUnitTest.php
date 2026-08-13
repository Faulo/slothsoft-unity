<?php
declare(strict_types = 1);

namespace Slothsoft\Unity;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Slothsoft\Unity\Command\Reporting\JUnitReportValidator;

class JUnitTest extends TestCase {
    
    const SCHEMA_DOCUMENT = 'farah://slothsoft@schema/schema/junit/1.0';
    
    const TEMPLATE_DOCUMENT = 'farah://slothsoft@unity/xsl/to-junit';
    
    const EXAMPLE_DIRECTORY = __DIR__ . '/../test-files/ValidTests';
    
    /**
     *
     * @dataProvider validTests
     */
    public function testTransformationIsValid(string $file): void {
        $dom = new DOMHelper();
        
        $data = $dom->transformToDocument($file, self::TEMPLATE_DOCUMENT);
        
        $result = $data->schemaValidate(self::SCHEMA_DOCUMENT);
        
        $this->assertTrue($result);
    }
    
    public function validTests(): iterable {
        foreach (FileSystem::scanDir(self::EXAMPLE_DIRECTORY, FileSystem::SCANDIR_REALPATH) as $file) {
            yield basename($file) => [
                $file
            ];
        }
    }

    public function testLegacyProcessTransformationRemainsStructurallyCompatible(): void {
        $data = $this->transformXml(<<<'XML'
        <result>
          <process package="Legacy.Package" name="Legacy operation" result="17" stdout="legacy stdout" stderr="legacy stderr" duration="1.25" start-time="2022-01-01T10:00:00Z">
            <failure type="LegacyFailure">legacy trace</failure>
          </process>
        </result>
        XML);
        $xpath = new DOMXPath($data);

        $this->assertSame(1, $xpath->query('/testsuites/testsuite')->length);
        $this->assertSame('', $xpath->evaluate('string(/testsuites/testsuite/@package)'));
        $this->assertSame('Legacy.Package', $xpath->evaluate('string(/testsuites/testsuite/@name)'));
        $this->assertSame('1', $xpath->evaluate('string(/testsuites/testsuite/@failures)'));
        $this->assertSame(0, $xpath->query('/testsuites/testsuite/properties/*')->length, 'Legacy process reports must not gain unity-command properties.');
        $this->assertSame('Legacy.Package', $xpath->evaluate('string(/testsuites/testsuite/testcase/@classname)'));
        $this->assertSame('Legacy operation', $xpath->evaluate('string(/testsuites/testsuite/testcase/@name)'));
        $failure = $xpath->query('/testsuites/testsuite/testcase/failure')->item(0);
        $this->assertInstanceOf(DOMElement::class, $failure);
        $this->assertSame('LegacyFailure', $failure->getAttribute('type'));
        $this->assertFalse($failure->hasAttribute('message'), 'Legacy failures are copied without synthesizing new attributes.');
        $this->assertSame('legacy trace', $failure->textContent);
        $this->assertSame('legacy stdout', $xpath->evaluate('string(/testsuites/testsuite/system-out)'));
        $this->assertSame('legacy stderr', $xpath->evaluate('string(/testsuites/testsuite/system-err)'));
    }

    public function testLegacyUnityTestTransformationRemainsStructurallyCompatible(): void {
        $data = $this->transformXml(<<<'XML'
        <test-run start-time="2022-01-01T10:00:00Z">
          <test-suite classname="Legacy.Suite" testcasecount="2" failed="0" skipped="0" inconclusive="0" duration="0.5" start-time="2022-01-01T10:00:00Z">
            <properties><property name="legacy" value="preserved" /></properties>
            <test-case classname="Legacy.Suite" name="LegacyCase" duration="0.25" result="Passed"><output>legacy case output</output></test-case>
          </test-suite>
        </test-run>
        XML);
        $xpath = new DOMXPath($data);

        $this->assertSame(1, $xpath->query('/testsuites/testsuite')->length);
        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite/@id)'));
        $this->assertSame('Legacy.Suite', $xpath->evaluate('string(/testsuites/testsuite/@name)'));
        $this->assertSame('2', $xpath->evaluate('string(/testsuites/testsuite/@tests)'), 'Legacy suite counts come from Unity Test Runner attributes.');
        $this->assertSame('preserved', $xpath->evaluate('string(/testsuites/testsuite/properties/property[@name="legacy"]/@value)'));
        $this->assertSame(1, $xpath->query('/testsuites/testsuite/testcase')->length);
        $this->assertSame('', $xpath->evaluate('string(/testsuites/testsuite/system-out)'), 'Legacy reports do not aggregate per-case output.');
    }

    public function testLegacyUnityTestTransformationMapsInconclusiveAndSkippedCasesForJenkins(): void {
        $data = $this->transformXml(<<<'XML'
        <test-run start-time="2022-01-01T10:00:00Z">
          <test-suite classname="Legacy.Suite" testcasecount="2" failed="0" skipped="1" inconclusive="1" duration="0.5" start-time="2022-01-01T10:00:00Z">
            <test-case classname="Legacy.Suite" name="Inconclusive" duration="0.25" result="Inconclusive">
              <reason><message>environment unavailable</message><stack-trace>inconclusive trace</stack-trace></reason>
              <output>inconclusive output</output>
            </test-case>
            <test-case classname="Legacy.Suite" name="Ignored" duration="0" result="Skipped" label="Ignored">
              <reason><message>disabled</message><stack-trace>ignored trace</stack-trace></reason>
              <output>ignored output</output>
            </test-case>
          </test-suite>
        </test-run>
        XML);
        (new JUnitReportValidator())->assertValid($data);
        $xpath = new DOMXPath($data);

        $this->assertSame('0', $xpath->evaluate('string(/testsuites/testsuite/@errors)'));
        $this->assertSame('2', $xpath->evaluate('string(/testsuites/testsuite/@skipped)'));
        $this->assertSame(2, $xpath->query('//testcase/skipped')->length);
        $this->assertSame('Inconclusive: environment unavailable', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/skipped/@message)'));
        $this->assertSame('inconclusive output', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/system-out)'));
        $this->assertSame('inconclusive trace', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/system-err)'));
        $this->assertSame('ignored output', $xpath->evaluate('string(//testcase[@name="Ignored"]/system-out)'));
        $this->assertSame('ignored trace', $xpath->evaluate('string(//testcase[@name="Ignored"]/system-err)'));
    }

    public function testLegacyDotNetTransformationRemainsAvailable(): void {
        $data = $this->transformXml(<<<'XML'
        <Reports Time="2024-05-30T14:14:43+02:00">
          <Report FileName="Example.cs" FilePath="/src/Example.cs">
            <FileChange LineNumber="2" CharNumber="3" FormatDescription="needs formatting" />
          </Report>
        </Reports>
        XML);
        $xpath = new DOMXPath($data);

        $this->assertSame('ContinuousIntegration', $xpath->evaluate('string(/testsuites/testsuite/@name)'));
        $this->assertSame('DotNet.Format', $xpath->evaluate('string(/testsuites/testsuite/testcase/@classname)'));
        $this->assertSame('VerifyNoChanges("Example.cs")', $xpath->evaluate('string(/testsuites/testsuite/testcase/@name)'));
        $this->assertSame('FormattingError', $xpath->evaluate('string(/testsuites/testsuite/testcase/failure/@type)'));
        $this->assertStringContainsString('needs formatting', $xpath->evaluate('string(/testsuites/testsuite/testcase/failure/@message)'));
    }

    private function transformXml(string $xml): DOMDocument {
        $source = new DOMDocument();
        $this->assertTrue($source->loadXML($xml));
        return (new DOMHelper())->transformToDocument($source, self::TEMPLATE_DOCUMENT);
    }
}
