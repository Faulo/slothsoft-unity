<?php
declare(strict_types = 1);

use Slothsoft\Unity\UnityHub;
use Slothsoft\Core\DOMHelper;

array_shift($_SERVER['argv']);
$_SERVER['argc'] --;

foreach ([
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../vendor/autoload.php'
] as $file) {
    if (file_exists($file)) {
        require_once $file;
        break;
    }
}

if ($_SERVER['argc'] < 2) {
    throw new \Exception("Missing arguments! Needs at least 2 (workspace and test mode).");
}

$args = $_SERVER['argv'];

$workspace = array_shift($args);

UnityHub::setLoggingEnabled(true);

$hub = new UnityHub();

if (! $hub->isInstalled()) {
    throw new \Exception("Failed to find Unity Hub!");
}

if (! is_dir($workspace)) {
    throw new \Exception("Workspace '$workspace' is not a directory!");
}

$workspace = realpath($workspace);

$project = $hub->findProject($workspace);

if (! $project) {
    throw new \Exception("Workspace '$workspace' does not contain a Unity project!");
}

if (! $project->ensureEditorIsInstalled()) {
    throw new \Exception("Editor installation for project '$project' failed!");
}

$reportDirectory = $workspace . DIRECTORY_SEPARATOR . 'test-reports';

if (! is_dir($reportDirectory)) {
    mkdir($reportDirectory, 0777, true);
}

$dom = new DOMHelper();

foreach ($args as $testMode) {
    $document = $project->runTests($testMode);

    $document = $dom->transformToDocument($document, 'farah://slothsoft@unity/xsl/to-junit');
    $document->formatOutput = true;
    $document->save($reportDirectory . DIRECTORY_SEPARATOR . "$testMode.xml");
}

return 0;