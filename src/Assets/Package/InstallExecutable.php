<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use DOMDocument;

class InstallExecutable extends ExecutableBase {

    protected function getExecutableCall(): string {
        return 'InstallPackage()';
    }

    protected function createSuccessDocument(): DOMDocument {
        return $this->createResultDocument(0, '', '', null);
    }
}
