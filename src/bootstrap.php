<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Farah\Module\Module;
use Slothsoft\Unity\UnityPackage;

Module::registerWithXmlManifestAndDefaultAssets('slothsoft@unity', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets');

UnityPackage::setEmptyManifestFile(__DIR__ . DIRECTORY_SEPARATOR . 'manifest.json');