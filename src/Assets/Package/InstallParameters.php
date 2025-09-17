<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

class InstallParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'package' => new StringSanitizer(''),
            'workspace' => new StringSanitizer('')
        ];
    }
}

