<?php
namespace Slothsoft\Devtools\Unity;

use Slothsoft\Devtools\CLI;
use DOMElement;

class UnityCourseStudent {

    public $owner;

    public $node;

    public $git;

    public function __construct(UnityCourse $owner, DOMElement $node) {
        $this->owner = $owner;
        $this->node = $node;
        $this->init();
    }

    private function init() {
        $name = $this->node->getAttribute('name');
        $path = $this->owner->settings['workspace'] . DIRECTORY_SEPARATOR . $this->owner->settings['project'] . '.' . $name;
        $results = $this->owner->resultsFolder . DIRECTORY_SEPARATOR . $name . '.xml';
        $this->node->setAttribute('path', $path);
        $this->node->setAttribute('results', $results);

        $path = $this->node->getAttribute('path');
        if (is_dir($path)) {
            $this->git = new GitProject($path);
        }
        if ($unity = $this->findUnityPath($path)) {
            $this->node->setAttribute('unity', $unity);
            $this->unity = new UnityProject($this->owner->settings['hub'], $unity);
        }
    }

    private function findUnityPath(string $path) {
        if (is_dir($path)) {
            $directory = new \RecursiveDirectoryIterator($path);
            $directoryIterator = new \RecursiveIteratorIterator($directory);
            foreach ($directoryIterator as $directory) {
                if ($directory->isDir()) {
                    $unity = $directory->getRealPath();
                    if (basename($unity) === 'Assets') {
                        return dirname($unity);
                    }
                }
            }
        }
        return null;
    }

    public function download() {
        $path = $this->node->getAttribute('path');
        if (! is_dir($path)) {
            $href = $this->node->getAttribute('href');
            $command = sprintf('git clone %s %s', escapeshellarg($href), escapeshellarg($path));
            CLI::execute($command);
            sleep(1);
            $this->init();
        }
    }

    public function runTests() {
        $results = $this->node->getAttribute('results');
        $this->unity->runTests($results, 'PlayMode');
    }
}