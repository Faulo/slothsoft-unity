<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

/**
 * Defines Farah URL parameters for empty-project requests.
 */
final class EmptyProjectParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'version' => new StringSanitizer('')
        ];
    }
}
