<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

/**
 * Defines Farah URL parameters for package installation requests.
 *
 * @author Daniel Schulz
 * @since 2022-07-11
 */
final class InstallParameters extends AbstractMapParameterFilter {
    
    /** @return array<string, \Slothsoft\Core\IO\Sanitizer\SanitizerInterface> */
    protected function createValueSanitizers(): array {
        return [
            'package' => new StringSanitizer(''),
            'workspace' => new StringSanitizer('')
        ];
    }
}
