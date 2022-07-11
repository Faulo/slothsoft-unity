<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class LocateHubFromCommand implements IHubLocator {

    /** @var string[] */
    private $command;

    public function __construct(array $command) {
        $this->command = $command;
    }

    public function create(array $arguments): Process {
        $arguments = array_merge($this->command, $arguments);
        return new Process($arguments);
    }

    public function exists(): bool {
        return true;
    }
}

