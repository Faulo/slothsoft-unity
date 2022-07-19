<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class ProcessRunner {

    /** @var Process */
    private Process $process;

    /** @var bool */
    private bool $loggingEnabled;

    public function __construct(Process $process, bool $loggingEnabled) {
        $this->process = $process;
        $this->loggingEnabled = $loggingEnabled;
    }

    public function run(): Process {
        if ($this->loggingEnabled) {
            fwrite(STDERR, $this->process->getCommandLine() . PHP_EOL);
        }

        $this->process->setTimeout(0);

        $this->process->run(function (string $type, string $data): void {
            if ($this->loggingEnabled or $type === Process::ERR) {
                fwrite(STDERR, $data);
            }
        });

        return $this->process;
    }
}

