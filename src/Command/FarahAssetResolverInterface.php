<?php
declare(strict_types = 1);

namespace Slothsoft\Unity\Command;

use DOMDocument;
use Slothsoft\Farah\FarahUrl\FarahUrl;

interface FarahAssetResolverInterface {

    public function resolve(FarahUrl $url): DOMDocument;
}
