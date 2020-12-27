<?php
namespace Slothsoft\CMS;

$projectName = trim($_REQUEST['project']);
if (! $projectName) {
    die('missing project name');
}

$buffer = __FILE__ . '.txt';

$input = @json_decode(file_get_contents('php://input'), true);

if ($input) {
    file_put_contents($buffer, json_encode($input));
} else {
    $input = @json_decode(file_get_contents($buffer), true);
}

if (isset($input['ref'])) {
    if (preg_match('~^refs/heads/(.+)$~', $input['ref'], $match)) {
        $branch = $match[1];
    }
    if (preg_match('~^refs/tags/(.+)$~', $input['ref'], $match)) {
        $branch = $match[1];
    }
} else {
    $branch = $input['repository']['default_branch'];
}

if (! $branch) {
    die('missing branch');
}

$_REQUEST['project'] = $projectName;
$_REQUEST['branch'] = $branch;
$_REQUEST['git'] = $input['repository']['clone_url'];

include (__DIR__ . DIRECTORY_SEPARATOR . 'pull.php');

$url = sprintf('http://slothsoft.net/getData.php/dev/unity/compile?project=%s&branch=%s', $_REQUEST['project'], $_REQUEST['branch']);
$cmd = sprintf('curl %s', escapeshellarg($url)) . PHP_EOL;
echo $cmd;
file_put_contents($queue, $cmd, FILE_APPEND | LOCK_EX);