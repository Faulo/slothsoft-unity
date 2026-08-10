<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Operation;

use DOMDocument;
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
            Command::FAILURE
        ];
        yield 'failed case without aggregate' => [
            '<test-run><test-suite><test-case result="Failed" /></test-suite></test-run>',
            Command::FAILURE
        ];
        yield 'underlying Unity exit code' => [
            '<test-run failed="1" unity-exit-code="42"><test-suite><test-case result="Failed" /></test-suite></test-run>',
            42
        ];
        yield 'malformed Unity exit code' => [
            '<test-run unity-exit-code="not-a-number" />',
            Command::FAILURE
        ];
        yield 'zero Unity exit code marker' => [
            '<test-run unity-exit-code="0" />',
            Command::FAILURE
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
    
    public function testFailedTestsCreateReportWithoutExecutingTwice(): void {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML(<<<'XML'
        <test-run start-time="2026-08-10T10:00:00Z" failed="1" inconclusive="0">
          <test-suite name="Fixture" classname="Example.Fixture" start-time="2026-08-10T10:00:00Z" duration="0.2">
            <test-case name="Fails" classname="Example.Fixture" result="Failed" duration="0.2">
              <failure><message>expected true</message><stack-trace>assertion trace</stack-trace></failure>
            </test-case>
          </test-suite>
        </test-run>
        XML);
        $executor = new SemanticTestsAssetExecutor(new AssetExecutionResult(Command::SUCCESS, $document));
        $tester = new ApplicationTester(ApplicationFactory::create([
            new TestsCommand($executor)
        ]));
        $path = temp_dir(__METHOD__) . DIRECTORY_SEPARATOR . 'tests.xml';
        
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
