<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Null object hub locator used when Unity Hub cannot be discovered.
 *
 * @author Daniel Schulz
 * @since 2022-07-11
 */
final class LocateHubNull implements HubLocatorInterface {
    
    public function create(array $arguments): Process {
        throw new FileNotFoundException('Unity is not installed!');
    }
    
    public function exists(): bool {
        return false;
    }
}
