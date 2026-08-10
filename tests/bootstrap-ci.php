<?php
declare(strict_types = 1);

use Slothsoft\Unity\LocateHubNull;
use Slothsoft\Unity\UnityHub;

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

UnityHub::setHubLocator(new LocateHubNull());
