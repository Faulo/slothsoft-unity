<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Assets\Package;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

/**
 * Defines Farah URL parameters for workspace-aware package installation.
 */
final class WorkspaceInstallParameters extends AbstractMapParameterFilter {
    
    protected function createValueSanitizers(): array {
        return [
            'workspace' => new StringSanitizer(''),
            'package' => new StringSanitizer('')
        ];
    }
}
