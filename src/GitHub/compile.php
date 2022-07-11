<?php
declare(strict_types = 1);
namespace Slothsoft\Unity\GitHub;

$queue = __DIR__ . '/queue.bat';

$projectName = trim($_REQUEST['project']);
if (! $projectName) {
    die('missing project name');
}

$targets = [
    [
        'platform' => 'WebGL',
        'folder' => "C:/Webserver/htdocs/vhosts/daniel-schulz.slothsoft.net/public/Builds/$projectName/",
        'file' => ''
    ],
    [
        'platform' => 'Win64',
        'folder' => "C:/NetzwerkDaten/Builds/$projectName/",
        'file' => "$projectName.exe"
        // 'backup' => "C:/NetzwerkDaten/Dropbox/Computerspielwissenschaften/$projectName/Builds/",
    ]
];

$workspaceDir = 'C:/Unity/workspace/';
$projectFile = 'ProjectSettings/ProjectVersion.txt';

// $url = "https://github.com/Faulo/$projectName";
$start = "$projectName.master";
$unityFile = 'C:/Unity/%s/Editor/Unity.exe';

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
    $projectDir = $projectName . '/' . $localBranch;
    $projectDir = str_replace('/', '.', $projectDir);
    $projectDir = $workspaceDir . $projectDir;
    $localFolder = str_replace('/', '.', $localBranch);
    foreach ($targets as $target) {
        $logFile = $target['folder'] . $localFolder . '.log';
        $outputDir = $target['folder'] . $localFolder;
        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        if (is_dir($projectDir)) {
            chdir($projectDir);

            $unityVersion = file_get_contents($projectFile);
            $match = [];
            if (preg_match('~m_EditorVersion: (.+)~', $unityVersion, $match)) {
                $unityVersion = trim($match[1]);

                $unity = sprintf($unityFile, $unityVersion);
                if (! realpath($unity)) {
                    die("Unity version $unityVersion must be installed at $unity");
                }
                $unity = realpath($unity);

                $cmd = sprintf('%s -quit -accept-apiupdate -batchmode -nographics -logFile %s -projectPath %s -executeMethod %s %s', $unity, escapeshellarg($logFile), escapeshellarg($projectDir), escapeshellarg('Slothsoft.UnityExtensions.Editor.Build.' . $target['platform']), escapeshellarg($outputDir . '/' . $target['file'])) . PHP_EOL;
                echo $cmd;
                file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);
                if (isset($target['backup']) and is_dir($target['backup'])) {
                    // copy last target to google drive
                    $cmd = sprintf('dcopy %s %s', escapeshellarg($outputDir), escapeshellarg($target['backup'] . $localFolder)) . PHP_EOL;
                    echo $cmd;
                    file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);
                }
            } else {
                die("Could not determine unity version from $projectFile");
            }
        }
    }
}

