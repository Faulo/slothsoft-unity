<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class LocateHubForWindows implements IHubLocator {

    private const REG_HUB_COMMAND = 'REG QUERY %s /v %s';

    private const REG_HUB_KEY = 'HKEY_LOCAL_MACHINE\\SOFTWARE\\Unity Technologies\\Hub';

    private const REG_HUB_VALUE = 'InstallLocation';

    private const HUB_EXECUTABLE = 'Unity Hub.exe';

    /** @var string */
    private $file = '';

    /** @var bool */
    private $exists = false;

    public function locate(): string {
        $this->init();
        return $this->file;
    }

    public function exists(): bool {
        $this->init();
        return $this->exists;
    }

    private function init() {
        if ($this->exists) {
            return;
        }
        $command = sprintf(self::REG_HUB_COMMAND, escapeshellarg(self::REG_HUB_KEY), escapeshellarg(self::REG_HUB_VALUE));
        $process = Process::fromShellCommandline($command);
        $process->run();
        $output = $process->getOutput();
        $output = explode('REG_SZ', $output, 2);
        if (count($output) !== 2) {
            return false;
        }
        $this->file = trim($output[1]) . DIRECTORY_SEPARATOR . self::HUB_EXECUTABLE;
        if ($file = realpath($this->file)) {
            $this->file = $file;
            $this->exists = true;
        }
    }
}

