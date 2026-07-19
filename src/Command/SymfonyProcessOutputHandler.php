<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final readonly class SymfonyProcessOutputHandler implements UnityProcessOutputHandlerInterface {
    
    public function __construct(
        private OutputInterface $standardOutput,
        private OutputInterface $errorOutput
    ) {
    }
    
    public function onProcessStarted(Process $process): void {
        $this->standardOutput->writeln($process->getCommandLine(), OutputInterface::OUTPUT_RAW);
    }
    
    public function onStandardOutput(string $data): void {
        $this->standardOutput->write($data, false, OutputInterface::OUTPUT_RAW);
    }
    
    public function onErrorOutput(string $data): void {
        $this->errorOutput->write($data, false, OutputInterface::OUTPUT_RAW);
    }
    
    public function onProcessFinished(Process $process): void {
        $exitCode = $process->getExitCode();
        $summary = sprintf('Process finished with exit code %s.', json_encode($exitCode));
        
        if ($exitCode === 0) {
            $this->standardOutput->writeln($summary, OutputInterface::OUTPUT_RAW);
        } else {
            $this->errorOutput->writeln($summary, OutputInterface::OUTPUT_RAW);
        }
    }
}
