<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

/**
 * Creates Unity Hub processes for a concrete platform or discovery strategy.
 *
 * @author Daniel Schulz
 * @since 2022-06-29
 */
interface HubLocatorInterface {
    
    public function create(array $arguments): Process;
    
    public function exists(): bool;
}
