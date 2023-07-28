<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

class SettingParameters extends AbstractMapParameterFilter {

    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'name' => new StringSanitizer('')
        ];
    }
}

