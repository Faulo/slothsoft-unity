<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets;

use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;
use Slothsoft\Core\IO\Sanitizer\FileNameSanitizer;

class HubParameterFilter extends AbstractMapParameterFilter {

    protected function createValueSanitizers(): array {
        return [
            'version' => new FileNameSanitizer(''),
            'modules' => new TokenSanitizer([])
        ];
    }
}

