<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use DateTimeImmutable;
use DOMDocument;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Unity\Command\Reporting\JUnitReporter;
use Slothsoft\Unity\Command\Reporting\OperationMetadata;
use Slothsoft\Unity\Command\Reporting\ReportError;
use Slothsoft\Unity\Command\Reporting\ReportingException;
use Slothsoft\Unity\ExecutionError;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RuntimeException;
use Throwable;

abstract class AbstractAssetCommand extends Command {
    
    private readonly AssetExecutorInterface $executor;
    
    private readonly JUnitReporter $reporter;
    
    public function __construct(AssetExecutorInterface $executor, public readonly ?string $name = null, ?JUnitReporter $reporter = null) {
        $this->executor = $executor;
        $this->reporter = $reporter ?? new JUnitReporter();
        parent::__construct($name);
        $this->addOption('junit', null, InputOption::VALUE_REQUIRED, 'Write a JUnit report to PATH; use "-" for stdout.');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $destination = $input->getOption('junit');
        $destination = is_string($destination) ? $destination : null;
        $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        if ($destination === null) {
            $result = $this->executor->execute($this->createAssetUrl($input), $output);
            $exitCode = $this->determineExitCode($result);
            $this->writeUnsuccessfulResultSummary($result, $exitCode, $errorOutput);
            return $exitCode;
        }

        $executionOutput = $destination === '-' ? $errorOutput : $output;
        $startedAt = new DateTimeImmutable();
        $startTime = microtime(true);
        $bufferLevel = ob_get_level();
        $reportXml = null;
        
        if ($destination === '-') {
            ob_start();
        }
        
        try {
            $result = $this->executeAsset($input, $executionOutput, $errorOutput);
            $exitCode = $this->determineExitCode($result);
            $this->writeUnsuccessfulResultSummary($result, $exitCode, $errorOutput);
            
            try {
                $metadata = $this->createMetadata($result, $startedAt, microtime(true) - $startTime, $exitCode);
                $report = $this->createReport($result, $metadata, $errorOutput, $exitCode);
                if ($destination === '-') {
                    $reportXml = $this->reporter->toXml($report);
                } else {
                    $this->reporter->write($report, $destination);
                }
            } catch (Throwable $error) {
                $errorOutput->writeln(sprintf('JUnit report failed: %s', $error->getMessage()), OutputInterface::OUTPUT_RAW);
                $exitCode = Command::FAILURE;
            }
        } finally {
            if ($destination === '-') {
                $capturedOutput = '';
                while (ob_get_level() > $bufferLevel) {
                    $chunk = ob_get_clean();
                    if (is_string($chunk)) {
                        $capturedOutput = $chunk . $capturedOutput;
                    }
                }
                if ($capturedOutput !== '') {
                    $errorOutput->write($capturedOutput, false, OutputInterface::OUTPUT_RAW);
                }
            }
        }
        
        if ($reportXml !== null) {
            $output->write($reportXml, false, OutputInterface::OUTPUT_RAW);
        }
        
        return $exitCode;
    }
    
    protected function determineExitCode(AssetExecutionResult $result): int {
        return $result->getExitCode();
    }
    
    abstract protected function createAssetUrl(InputInterface $input): FarahUrl;
    
    private function executeAsset(InputInterface $input, OutputInterface $output, OutputInterface $errorOutput): AssetExecutionResult {
        try {
            return $this->executor->execute($this->createAssetUrl($input), $output);
        } catch (Throwable $error) {
            $errorOutput->writeln(sprintf('Command failed: %s', $error->getMessage()), OutputInterface::OUTPUT_RAW);
            return new AssetExecutionResult(Command::FAILURE, null, $error);
        }
    }

    private function writeUnsuccessfulResultSummary(AssetExecutionResult $result, int $exitCode, OutputInterface $errorOutput): void {
        if ($exitCode !== Command::SUCCESS and $result->getExitCode() === Command::SUCCESS) {
            $errorOutput->writeln(sprintf('Command failed with exit code %d: %s reported an unsuccessful result.', $exitCode, $this->getName()), OutputInterface::OUTPUT_RAW);
        }
    }
    
    private function createMetadata(AssetExecutionResult $result, DateTimeImmutable $startedAt, float $duration, int $exitCode): OperationMetadata {
        $standardOutput = '';
        $standardError = '';
        $error = $result->getError();
        if ($error instanceof ExecutionError) {
            $standardOutput = $error->getStdOut();
            $standardError = $error->getStdErr();
        }
        
        return new OperationMetadata(
            $this->getName() ?? static::class,
            startedAt: $startedAt,
            duration: $duration,
            standardOutput: $standardOutput,
            standardError: $standardError,
            exitCode: $exitCode
        );
    }
    
    private function createReport(AssetExecutionResult $result, OperationMetadata $metadata, OutputInterface $errorOutput, int &$exitCode): DOMDocument {
        if ($document = $result->getDocument()) {
            try {
                $report = $this->reporter->createReport($document, $metadata);
            } catch (ReportingException $error) {
                if ($result->getExitCode() !== Command::SUCCESS or $exitCode === Command::SUCCESS) {
                    throw $error;
                }
                return $this->createSemanticFailureReport($metadata, $error->getMessage());
            }
            if ($result->getExitCode() === Command::SUCCESS and $exitCode !== Command::SUCCESS and ! $this->containsJUnitProblem($report)) {
                return $this->createSemanticFailureReport($metadata);
            }
            return $report;
        }
        if ($error = $result->getError()) {
            return $this->reporter->createErrorReport($metadata, ReportError::fromThrowable($error));
        }
        
        $message = sprintf("Command '%s' did not produce an XML result for JUnit reporting.", $this->getName());
        $errorOutput->writeln('Command failed: ' . $message, OutputInterface::OUTPUT_RAW);
        $exitCode = Command::FAILURE;
        return $this->reporter->createErrorReport($metadata, ReportError::error(RuntimeException::class, $message));
    }
    
    private function createSemanticFailureReport(OperationMetadata $metadata, string $details = ''): DOMDocument {
        $message = sprintf("Command '%s' produced an invalid or unsuccessful semantic result.", $this->getName());
        return $this->reporter->createErrorReport($metadata, ReportError::failure('SemanticFailure', $message, $details));
    }
    
    private function containsJUnitProblem(DOMDocument $report): bool {
        return $report->getElementsByTagName('failure')->length > 0 or $report->getElementsByTagName('error')->length > 0;
    }
}
