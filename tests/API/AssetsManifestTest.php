<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\API;

use Slothsoft\FarahTesting\Module\AbstractXmlManifestTest;

class AssetsManifestTest extends AbstractXmlManifestTest {
    
    protected static function getManifestDirectory(): string {
        return dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'assets';
    }
}