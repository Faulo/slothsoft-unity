<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command\Reporting;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\Command\AbstractAssetCommand;
use Slothsoft\Unity\Command\ApplicationFactory;
use Slothsoft\Unity\Command\AssetExecutionResult;
use Slothsoft\Unity\Command\AssetExecutorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

final class CommandReportingIntegrationTest extends TestCase {
    
    public function testFileReportPreservesLiveOutputAndExecutesOnce(): void {
        $executor = new ReportingFixtureExecutor();
        $tester = $this->createTester($executor);
        $path = temp_dir(__METHOD__) . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'report.xml';
        
        $code = $tester->run([
            'command' => 'fixture',
            'workspace' => 'workspace',
            '--junit' => $path
        ], $this->testerOptions());
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(1, $executor->executionCount);
        $this->assertStringContainsString("live stdout\n", $tester->getDisplay());
        $this->assertStringContainsString("live stderr\n", $tester->getErrorOutput());
        $this->assertStringNotContainsString('internal-result', $tester->getDisplay());
        $this->assertFileExists($path);
        $this->assertValidJUnit(file_get_contents($path));
    }
    
    public function testStdoutReportContainsOnlyXmlAndRedirectsAllNormalOutput(): void {
        $executor = new ReportingFixtureExecutor(true);
        $tester = $this->createTester($executor);
        
        $code = $tester->run([
            'command' => 'fixture',
            'workspace' => 'workspace',
            '--junit' => '-'
        ], $this->testerOptions());
        
        $this->assertSame(Command::SUCCESS, $code);
        $this->assertSame(1, $executor->executionCount);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $tester->getDisplay());
        $this->assertStringNotContainsString('live stdout', $tester->getDisplay());
        $this->assertStringNotContainsString('library output', $tester->getDisplay());
        $this->assertStringNotContainsString('internal-result', $tester->getDisplay());
        $this->assertStringContainsString("live stdout\n", $tester->getErrorOutput());
        $this->assertStringContainsString("live stderr\n", $tester->getErrorOutput());
        $this->assertStringContainsString("library output\n", $tester->getErrorOutput());
        $this->assertValidJUnit($tester->getDisplay());
    }
    
    public function testExecutionExceptionStillCreatesRequestedReport(): void {
        $executor = new ReportingFixtureExecutor(false, true);
        $tester = $this->createTester($executor);
        $path = temp_dir(__METHOD__) . DIRECTORY_SEPARATOR . 'failure.xml';
        
        $code = $tester->run([
            'command' => 'fixture',
            'workspace' => 'workspace',
            '--junit' => $path
        ], $this->testerOptions());
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame(1, $executor->executionCount);
        $this->assertStringContainsString('synthetic execution failure', $tester->getErrorOutput());
        $xml = file_get_contents($path);
        $this->assertStringContainsString('synthetic execution failure', $xml);
        $this->assertValidJUnit($xml);
    }
    
    public function testReportWriteFailureOverridesOperationResult(): void {
        $executor = new ReportingFixtureExecutor();
        $tester = $this->createTester($executor);
        $directory = temp_dir(__METHOD__);
        
        $code = $tester->run([
            'command' => 'fixture',
            'workspace' => 'workspace',
            '--junit' => $directory
        ], $this->testerOptions());
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame(1, $executor->executionCount);
        $this->assertStringContainsString('JUnit report failed:', $tester->getErrorOutput());
    }
    
    public function testMissingArgumentReturnsInvalidAndCreatesFileReport(): void {
        $executor = new ReportingFixtureExecutor();
        $tester = $this->createTester($executor);
        $path = temp_dir(__METHOD__) . DIRECTORY_SEPARATOR . 'validation.xml';
        
        $code = $tester->run([
            'command' => 'fixture',
            '--junit' => $path
        ], $this->testerOptions());
        
        $this->assertSame(Command::INVALID, $code);
        $this->assertSame(0, $executor->executionCount);
        $this->assertStringContainsString('Not enough arguments', $tester->getErrorOutput());
        $this->assertValidJUnit(file_get_contents($path));
    }
    
    public function testUnknownCommandReturnsInvalidAndCreatesCleanStdoutReport(): void {
        $executor = new ReportingFixtureExecutor();
        $tester = $this->createTester($executor);
        
        $code = $tester->run([
            'command' => 'unknown-command',
            '--junit' => '-'
        ], $this->testerOptions());
        
        $this->assertSame(Command::INVALID, $code);
        $this->assertSame(0, $executor->executionCount);
        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $tester->getDisplay());
        $this->assertStringContainsString('Command "unknown-command" is not defined.', $tester->getErrorOutput());
        $this->assertValidJUnit($tester->getDisplay());
    }
    
    public function testUnrepresentedSemanticFailureCreatesFailingReport(): void {
        $executor = new ReportingFixtureExecutor();
        $tester = new ApplicationTester(ApplicationFactory::createReporting([
            new SemanticFailureFixtureCommand($executor)
        ]));
        $path = temp_dir(__METHOD__) . DIRECTORY_SEPARATOR . 'semantic.xml';
        
        $code = $tester->run([
            'command' => 'semantic-fixture',
            'workspace' => 'workspace',
            '--junit' => $path
        ], $this->testerOptions());
        
        $this->assertSame(Command::FAILURE, $code);
        $this->assertSame(1, $executor->executionCount);
        $report = new DOMDocument();
        $this->assertTrue($report->load($path));
        $xpath = new \DOMXPath($report);
        $this->assertSame('SemanticFailure', $xpath->evaluate('string(/testsuites/testsuite/testcase/failure/@type)'));
        $xml = $report->saveXML();
        $this->assertIsString($xml);
        $this->assertValidJUnit($xml);
    }
    
    private function createTester(ReportingFixtureExecutor $executor): ApplicationTester {
        return new ApplicationTester(ApplicationFactory::createReporting([
            new ReportingFixtureCommand($executor)
        ]));
    }
    
    private function testerOptions(): array {
        return [
            'capture_stderr_separately' => true,
            'decorated' => false
        ];
    }
    
    private function assertValidJUnit(string $xml): void {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml));
        (new JUnitReportValidator())->assertValid($document);
    }
}

final class ReportingFixtureCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'fixture');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->addArgument('workspace', InputArgument::REQUIRED);
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromReference('farah://slothsoft@unity/project/method');
    }
}

final class SemanticFailureFixtureCommand extends AbstractAssetCommand {
    
    public function __construct(AssetExecutorInterface $executor) {
        parent::__construct($executor, 'semantic-fixture');
    }
    
    protected function configure(): void {
        parent::configure();
        $this->addArgument('workspace', InputArgument::REQUIRED);
    }
    
    protected function createAssetUrl(InputInterface $input): FarahUrl {
        return FarahUrl::createFromReference('farah://slothsoft@unity/project/method');
    }
    
    protected function determineExitCode(AssetExecutionResult $result): int {
        return Command::FAILURE;
    }
}

final class ReportingFixtureExecutor implements AssetExecutorInterface {
    
    public int $executionCount = 0;
    
    public function __construct(
        private readonly bool $emitDirectOutput = false,
        private readonly bool $throw = false
    ) {
    }
    
    public function execute(FarahUrl $url, OutputInterface $output): AssetExecutionResult {
        $this->executionCount ++;
        $output->write("live stdout\n", false, OutputInterface::OUTPUT_RAW);
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $errorOutput->write("live stderr\n", false, OutputInterface::OUTPUT_RAW);
        if ($this->emitDirectOutput) {
            echo "library output\n";
        }
        if ($this->throw) {
            throw new RuntimeException('synthetic execution failure');
        }
        
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadXML('<command-success><internal-result>secret</internal-result></command-success>');
        return new AssetExecutionResult(Command::SUCCESS, $document);
    }
}
