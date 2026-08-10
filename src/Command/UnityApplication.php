<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use DOMDocument;
use Slothsoft\Unity\Command\Reporting\JUnitReporter;
use Slothsoft\Unity\Command\Reporting\OperationMetadata;
use Slothsoft\Unity\Command\Reporting\ReportError;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Symfony application that also reports command-line validation failures.
 */
final class UnityApplication extends Application {
    
    public function __construct(private readonly JUnitReporter $reporter) {
        parent::__construct('unity-command');
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int {
        return parent::run($input ?? new UnityArgvInput(), $output);
    }
    
    public function doRun(InputInterface $input, OutputInterface $output): int {
        try {
            return parent::doRun($input, $output);
        } catch (ExceptionInterface $error) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $this->renderThrowable($error, $errorOutput);
            $destination = $this->findReportDestination($input);
            if ($destination === null) {
                return Command::INVALID;
            }
            
            try {
                $metadata = new OperationMetadata($input->getFirstArgument() ?? 'input', exitCode: Command::INVALID);
                $report = $this->reporter->createErrorReport($metadata, ReportError::fromThrowable($error));
                $this->publishReport($report, $destination, $output);
            } catch (Throwable $reportError) {
                $errorOutput->writeln(sprintf('JUnit report failed: %s', $reportError->getMessage()), OutputInterface::OUTPUT_RAW);
                return Command::FAILURE;
            }
            
            return Command::INVALID;
        }
    }
    
    private function findReportDestination(InputInterface $input): ?string {
        if (! $input->hasParameterOption('--junit', true)) {
            return null;
        }
        
        $destination = $input->getParameterOption('--junit', null, true);
        return is_string($destination) && $destination !== '' ? $destination : null;
    }
    
    private function publishReport(DOMDocument $report, string $destination, OutputInterface $output): void {
        if ($destination === '-') {
            $output->write($this->reporter->toXml($report), false, OutputInterface::OUTPUT_RAW);
        } else {
            $this->reporter->write($report, $destination);
        }
    }
}
