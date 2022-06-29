<?php
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

interface IHubLocator {

    public function create(array $arguments): Process;

    public function exists(): bool;
}
