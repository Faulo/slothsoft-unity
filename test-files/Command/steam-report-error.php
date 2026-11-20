<?php
declare(strict_types = 1);

use Slothsoft\Unity\Steam\SteamCMD;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$password = (string) getenv('TEST_STEAM_PASSWORD');
$code = (string) getenv('TEST_STEAM_GUARD_CODE');
$process = new Process([PHP_BINARY, __DIR__ . '/echo-arguments.php', '0', $password, $code]);
$process->run();

(new ReflectionMethod(SteamCMD::class, 'reportError'))->invoke(new SteamCMD(), $process, $password, $code);
