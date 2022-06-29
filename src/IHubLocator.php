<?php
namespace Slothsoft\Unity;

interface IHubLocator {

    public function locate(): string;

    public function exists(): bool;
}
