<?php
namespace Slothsoft\Unity\Assets\Package;

use DOMDocument;

class InstallExecutable extends ExecutableBase {

    protected function getExecutableCall(): string {
        return 'InstallPackage()';
    }

    protected function createSuccessDocument(): DOMDocument {
        return $this->createResultDocument(0, '', '');
    }
}
