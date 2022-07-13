<?php
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;
use Generator;

class ProcessRunner {

    /** @var Process */
    private Process $process;

    /** @var bool */
    private bool $loggingEnabled;

    public function __construct(Process $process, bool $loggingEnabled) {
        $this->process = $process;
        $this->loggingEnabled = $loggingEnabled;
    }

    public function toString(): string {
        $result = '';
        foreach ($this->toGenerator() as $value) {
            $result .= $value;
        }
        return trim($result);
    }

    public function toGenerator(): Generator {
        if ($this->loggingEnabled) {
            echo $this->process->getCommandLine() . PHP_EOL;
        }
        $this->process->setTimeout(0);
        $this->process->start();
        foreach ($this->process as $type => $data) {
            if ($this->loggingEnabled) {
                echo $data;
            }
            if ($type === Process::OUT) {
                yield $data;
            }
            if ($type === Process::ERR) {
                fwrite(STDERR, $data);
            }
        }
    }
}

