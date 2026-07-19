<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use Symfony\Component\Process\Process;

interface UnityProcessOutputHandlerInterface {
    
    public function onProcessStarted(Process $process): void;
    
    public function onStandardOutput(string $data): void;
    
    public function onErrorOutput(string $data): void;
    
    public function onProcessFinished(Process $process): void;
}
