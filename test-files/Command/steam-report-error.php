<?php
declare(strict_types = 1);

use Slothsoft\Unity\Steam\SteamCMD;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$password = (string) getenv(SteamCMD::STEAM_CREDENTIALS_PSW);
$process = new Process([PHP_BINARY, __DIR__ . '/echo-arguments.php', '0', $password]);
$process->run();

(new ReflectionMethod(SteamCMD::class, 'reportError'))->invoke(new SteamCMD(), $process);
