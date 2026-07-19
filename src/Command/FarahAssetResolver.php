<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use DOMDocument;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\Module\Module;

final readonly class FarahAssetResolver implements FarahAssetResolverInterface {

    public function resolve(FarahUrl $url): DOMDocument {
        return Module::resolveToDOMWriter($url)->toDocument();
    }
}
