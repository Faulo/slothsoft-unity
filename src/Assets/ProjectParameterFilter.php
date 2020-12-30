<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Core\IO\Sanitizer\FileNameSanitizer;
use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;
use Slothsoft\Core\IO\Sanitizer\IntegerSanitizer;

class ProjectParameterFilter extends AbstractMapParameterFilter {

    protected function createValueSanitizers(): array {
        return [
            'id' => new FileNameSanitizer(''),
            'href' => new StringSanitizer(''),
            'branch' => new StringSanitizer('master'),
            'debug' => new IntegerSanitizer(0)
        ];
    }
}

