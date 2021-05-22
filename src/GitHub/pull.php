<?php
namespace Slothsoft\Unity\GitHub;

$queue = __DIR__ . '/queue.bat';

$projectName = trim($_REQUEST['project']);
if (! $projectName) {
    die('missing project name');
}

$workspaceDir = realpath('C:/Unity/workspace') . DIRECTORY_SEPARATOR;

$start = "$projectName.master";

$branches = [];
if (isset($_REQUEST['branch'])) {
    $branches[] = 'origin/' . $_REQUEST['branch'];
} else {
    chdir($workspaceDir . $start);

    $output = [];
    exec('git branch -r', $output);
    array_shift($output);
    foreach ($output as $val) {
        $branches[] = trim($val);
    }
}

foreach ($branches as $branch) {
    $localBranch = substr($branch, strlen('origin/'));
    $folder = $projectName . '/' . $localBranch;
    $folder = str_replace('/', '.', $folder);
    $folder = $workspaceDir . $folder;
    if (is_dir($folder)) {
        $cmd = sprintf('git -C %s reset --hard', escapeshellarg($folder)) . PHP_EOL;
        echo $cmd;
        file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);
        $cmd = sprintf('git -C %s pull origin %s -f', escapeshellarg($folder), escapeshellarg($localBranch)) . PHP_EOL;
        echo $cmd;
        file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);
    } else {
        $url = $_REQUEST['git'];
        $cmd = sprintf('git clone %s --single-branch --branch %s %s', escapeshellarg($url), escapeshellarg($localBranch), escapeshellarg($folder)) . PHP_EOL;
        echo $cmd;
        file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);
    }
}