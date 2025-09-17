<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\IntegerSanitizer;
use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;
use Slothsoft\Core\IO\Sanitizer\ArraySanitizer;

class MethodParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'method' => new StringSanitizer(''),
            'quit' => new IntegerSanitizer(1),
            'args' => new ArraySanitizer()
        ];
    }
}

