<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Slothsoft\Unity\UnityEnvironment;
use Slothsoft\Unity\UnityHub;
use Symfony\Component\Process\Process;

final readonly class DefaultUnityProcessOutputHandler implements UnityProcessOutputHandlerInterface {
    
    public function onProcessStarted(Process $process): void {
        if (UnityHub::getLoggingEnabled() or UnityEnvironment::isLoggingInput()) {
            fwrite(STDERR, UnityEnvironment::formatInput($process->getCommandLine() . PHP_EOL));
        }
    }
    
    public function onStandardOutput(string $data): void {
        if (UnityHub::getLoggingEnabled() or UnityEnvironment::isLoggingOutput()) {
            fwrite(STDERR, UnityEnvironment::formatOutput($data));
        }
    }
    
    public function onErrorOutput(string $data): void {
        if (UnityHub::getLoggingEnabled() or UnityEnvironment::isLoggingError()) {
            fwrite(STDERR, UnityEnvironment::formatError($data));
        }
    }
    
    public function onProcessFinished(Process $process): void {
    }
}
