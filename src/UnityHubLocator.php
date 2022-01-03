<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class UnityHubLocator {
    const REG_HUB_COMMAND = 'REG QUERY %s /v %s';
    const REG_HUB_KEY = 'HKEY_LOCAL_MACHINE\\SOFTWARE\\Unity Technologies\\Hub';
    const REG_HUB_VALUE = 'InstallLocation';
    const HUB_EXECUTABLE = 'Unity Hub.exe';
    
    public function findHubLocation() : string {
        $command = sprintf(self::REG_HUB_COMMAND, escapeshellarg(self::REG_HUB_KEY), escapeshellarg(self::REG_HUB_VALUE));
        $process = Process::fromShellCommandline($command);
        $process->run();
        $output = $process->getOutput();
        $output = explode('REG_SZ', $output, 2);
        if (count($output) !== 2) {
            throw new \RuntimeException("Failed to determine Unity Hub's installation path!");
        }
        return trim($output[1]) . DIRECTORY_SEPARATOR . self::HUB_EXECUTABLE;
    }
}

