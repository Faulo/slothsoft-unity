<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Project;

use Slothsoft\Core\IO\Sanitizer\ArraySanitizer;
use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

/**
 * Defines Farah URL parameters for Unity Test Runner requests.
 *
 * @author Daniel Schulz
 * @since 2022-07-11
 */
final class TestsParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'modes' => new ArraySanitizer()
        ];
    }
}
