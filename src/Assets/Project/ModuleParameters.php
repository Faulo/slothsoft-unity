<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\ArraySanitizer;
use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

class ModuleParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(),
            'modules' => new ArraySanitizer()
        ];
    }
}

