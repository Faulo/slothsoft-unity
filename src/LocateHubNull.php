<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class LocateHubNull implements HubLocatorInterface {

    public function create(array $arguments): Process {
        throw new FileNotFoundException('Unity is not installed!');
    }

    public function exists(): bool {
        return false;
    }
}

