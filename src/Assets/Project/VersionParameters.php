<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

/**
 * Defines Farah URL parameters for project version requests.
 *
 * @author Daniel Schulz
 * @since 2022-07-11
 */
final class VersionParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'mode' => new StringSanitizer(''),
            'version' => new StringSanitizer('')
        ];
    }
}
