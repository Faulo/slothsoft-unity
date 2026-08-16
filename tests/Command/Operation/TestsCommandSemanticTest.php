<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\Command\ApplicationFactory;
use Slothsoft\Unity\Command\AssetExecutionResult;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Slothsoft\Unity\Command\Reporting\JUnitReportValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

final class TestsCommandSemanticTest extends TestCase {
    
    /**
     * @dataProvider semanticResultProvider
     */
    public function testSemanticResultDeterminesExitCode(string $xml, int $expectedCode): void {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML($xml);
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand(new SemanticTestsAssetExecutor(new AssetExecutionResult(Command::SUCCESS, $document)))
        ]));
        
        $code = $tester->run([
            'command' => 'tests',
            'workspace' => 'workspace',
            'modes' => [
                'EditMode'
            ]
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame($expectedCode, $code);
        if ($expectedCode === Command::SUCCESS) {
            $this->assertSame('', $tester->getErrorOutput());
        } else {
            $this->assertStringContainsString('reported an unsuccessful result', $tester->getErrorOutput());
        }
    }
    
    public function semanticResultProvider(): iterable {
        yield 'passing' => [
            '<test-run failed="0" inconclusive="0"><test-suite><test-case result="Passed" /></test-suite></test-run>',
            Command::SUCCESS
        ];
        yield 'skipped' => [
            '<test-run failed="0" inconclusive="0"><test-suite><test-case result="Skipped" /></test-suite></test-run>',
            Command::SUCCESS
        ];
        yield 'failed aggregate' => [
            '<test-run failed="1" inconclusive="0"><test-suite><test-case result="Failed" /></test-suite></test-run>',
            Command::FAILURE
        ];
        yield 'inconclusive aggregate' => [
            '<test-run failed="0" inconclusive="1"><test-suite><test-case result="Inconclusive" /></test-suite></test-run>',
            Command::SUCCESS
        ];
        yield 'inconclusive case without aggregate' => [
            '<test-run><test-suite><test-case result="Inconclusive" /></test-suite></test-run>',
            Command::SUCCESS
        ];
        yield 'failed case without aggregate' => [
            '<test-run><test-suite><test-case result="Failed" /></test-suite></test-run>',
            Command::FAILURE
        ];
        yield 'underlying Unity exit code' => [
            '<test-run failed="1" unity-exit-code="42"><test-suite><test-case result="Failed" /></test-suite></test-run>',
            Command::FAILURE
        ];
        yield 'valid report ignores diagnostic Unity exit code' => [
            '<test-run failed="0" unity-exit-code="2"><test-suite><test-case result="Passed" /></test-suite></test-run>',
            Command::SUCCESS
        ];
        yield 'malformed Unity exit code' => [
            '<test-run unity-exit-code="not-a-number" />',
            Command::SUCCESS
        ];
        yield 'zero Unity exit code marker' => [
            '<test-run unity-exit-code="0" />',
            Command::SUCCESS
        ];
        yield 'unexpected result root' => [
            '<result />',
            Command::FAILURE
        ];
    }
    
    public function testUnityExitCodeTakesPrecedenceOverSemanticResult(): void {
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand(new SemanticTestsAssetExecutor(new AssetExecutionResult(42)))
        ]));
        
        $code = $tester->run([
            'command' => 'tests',
            'workspace' => 'workspace',
            'modes' => [
                'EditMode'
            ]
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(42, $code);
        $this->assertSame('', $tester->getErrorOutput());
    }

    public function testInconclusiveTestsCreateSuccessfulSkippedReport(): void {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(<<<'XML'
        <test-run start-time="2026-08-10T10:00:00Z" failed="0" inconclusive="1">
          <test-suite name="Fixture" classname="Example.Fixture" start-time="2026-08-10T10:00:00Z" duration="0.2">
            <test-case name="Passes" classname="Example.Fixture" result="Passed" duration="0.1" />
            <test-case name="Inconclusive" classname="Example.Fixture" result="Inconclusive" duration="0.1">
              <reason><message>environment unavailable</message><stack-trace>diagnostic trace</stack-trace></reason>
              <output>captured output</output>
            </test-case>
          </test-suite>
        </test-run>
        XML);
        $executor = new SemanticTestsAssetExecutor(new AssetExecutionResult(Command::SUCCESS, $document));
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand($executor)
        ]));
        $path = temp_dir(str_replace(':', '-', __METHOD__)) . DIRECTORY_SEPARATOR . 'tests.xml';

        $code = $tester->run([
            'command' => 'tests',
            'workspace' => 'workspace',
            'modes' => [
                'EditMode'
            ],
            '--junit' => $path
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(1, $executor->executionCount);
        $report = new DOMDocument();
        $this->assertTrue($report->load($path));
        (new JUnitReportValidator())->assertValid($report);
        $xpath = new DOMXPath($report);
        $this->assertSame(0, $xpath->query('//failure | //error')->length);
        $this->assertSame('Inconclusive: environment unavailable', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/skipped/@message)'));
        $this->assertSame('captured output', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/system-out)'));
        $this->assertSame('diagnostic trace', $xpath->evaluate('string(//testcase[@name="Inconclusive"]/system-err)'));
    }

    public function testValidReportWarningKeepsStdoutReportAsPureXml(): void {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(<<<'XML'
        <test-run failed="0" start-time="2026-08-16T10:00:00Z">
          <warning>Unity test mode 'PlayMode' produced a valid report despite process exit code 2.</warning>
          <test-suite name="Fixture" classname="Example.Fixture" start-time="2026-08-16T10:00:00Z">
            <test-case name="Passes" classname="Example.Fixture" result="Passed" duration="0" />
          </test-suite>
        </test-run>
        XML);
        $executor = new SemanticTestsAssetExecutor(new AssetExecutionResult(Command::SUCCESS, $document));
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand($executor)
        ]));

        $code = $tester->run([
            'command' => 'tests',
            'workspace' => 'workspace',
            'modes' => [
                'PlayMode'
            ],
            '--junit' => '-'
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $tester->getDisplay());
        $this->assertStringNotContainsString('WARNING:', $tester->getErrorOutput());
        $report = new DOMDocument();
        $this->assertTrue($report->loadXML($tester->getDisplay()));
        (new JUnitReportValidator())->assertValid($report);
        $xpath = new DOMXPath($report);
        $this->assertSame("Unity test mode 'PlayMode' produced a valid report despite process exit code 2.", $xpath->evaluate('string(//property[@name="unity-command.warning.1"]/@value)'));
        $this->assertStringContainsString('WARNING: Unity test mode', $xpath->evaluate('string(/testsuites/testsuite/system-err)'));
    }
    
    public function testFailedTestsCreateReportWithoutExecutingTwice(): void {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(<<<'XML'
        <test-run start-time="2026-08-10T10:00:00Z" failed="1" inconclusive="1">
          <test-suite name="Fixture" classname="Example.Fixture" start-time="2026-08-10T10:00:00Z" duration="0.2">
            <test-case name="Fails" classname="Example.Fixture" result="Failed" duration="0.2">
              <failure><message>expected true</message><stack-trace>assertion trace</stack-trace></failure>
            </test-case>
            <test-case name="Inconclusive" classname="Example.Fixture" result="Inconclusive" duration="0">
              <reason><message>environment unavailable</message><stack-trace>diagnostic trace</stack-trace></reason>
            </test-case>
          </test-suite>
        </test-run>
        XML);
        $executor = new SemanticTestsAssetExecutor(new AssetExecutionResult(Command::SUCCESS, $document));
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand($executor)
        ]));
        $path = temp_dir(str_replace(':', '-', __METHOD__)) . DIRECTORY_SEPARATOR . 'tests.xml';
        
        $code = $tester->run([
            'command' => 'tests',
            'workspace' => 'workspace',
            'modes' => [
                'EditMode'
            ],
            '--junit' => $path
        ], [
            'capture_stderr_separately' => true,
            'decorated' => false
        ]);
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame(1, $executor->executionCount);
        $report = new DOMDocument();
        $this->assertTrue($report->load($path));
        (new JUnitReportValidator())->assertValid($report);
        $this->assertSame(1, $report->getElementsByTagName('failure')->length);
        $this->assertSame(0, $report->getElementsByTagName('error')->length);
        $this->assertSame(1, $report->getElementsByTagName('skipped')->length);
    }
}

final class SemanticTestsAssetExecutor implements AssetExecutorInterface {
    
    public int $executionCount;
    
    public function __construct(private AssetExecutionResult $result) {
        $this->executionCount = 0;
    }
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult {
        $this->executionCount ++;
        return $this->result;
    }
}
