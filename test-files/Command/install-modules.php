<?php
declare(strict_types = 1);

$manifestPath = $argv[1];
$modulesToInstall = json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

foreach ($manifest as &$module) {
    if (in_array($module['id'], $modulesToInstall, true)) {
        $module['selected'] = true;
    }
}
unset($module);

file_put_contents($manifestPath, json_encode($manifest, JSON_THROW_ON_ERROR));
exit((int) $argv[3]);
