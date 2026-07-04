<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

/**
 * Locates Unity Hub through a configured command line.
 *
 * @author Daniel Schulz
 * @since 2022-06-29
 */
final class LocateHubFromCommand implements HubLocatorInterface {
    
    private array $command;
    
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
