<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

class LocateHubFromCommand implements IHubLocator {

    /** @var string */
    private $command;

    public function __construct(string $command) {
        $this->command = $command;
    }

    public function locate(): string {
        return $this->command;
    }

    public function exists(): bool {
        return true;
    }
}

