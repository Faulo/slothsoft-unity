<?php
namespace Slothsoft\Devtools\Unity;

use Slothsoft\Devtools\CLI;

class UnityProject {

    const FILE_UNITY = '%s/%s/Editor/Unity.exe';

    const FILE_VERSION = '/ProjectSettings/ProjectVersion.txt';

    const FILE_PROJECT = '/ProjectSettings/ProjectSettings.asset';

    const FILE_PACKAGES = '/Packages/packages-lock.json';

    private $projectPath;

    private $unityVersion;

    private $unityPath;

    public $companyName;

    public $packages = [];

    public function __construct(string $unityPath, string $projectPath) {
        assert(is_dir($unityPath), "Path $unityPath not found");
        assert(is_dir($projectPath), "Path $projectPath not found");

        $this->unityPath = realpath($unityPath);
        $this->projectPath = realpath($projectPath);
        $this->loadProject();
    }

    private function loadProject() {
        $match = [];

        $contents = $this->loadFile($this->projectPath . self::FILE_VERSION);
        if (preg_match('~m_EditorVersion: (.+)~', $contents, $match)) {
            $this->unityVersion = trim($match[1]);

            $unityFile = sprintf(self::FILE_UNITY, $this->unityPath, $this->unityVersion);
            assert(is_file($unityFile), "File $unityFile not found");
            $this->unityFile = realpath($unityFile);
        }

        $contents = $this->loadFile($this->projectPath . self::FILE_PROJECT);
        if (preg_match('~companyName: (.+)~', $contents, $match)) {
            $this->companyName = trim($match[1]);
        }

        $tmp = json_decode($this->loadFile($this->projectPath . self::FILE_PACKAGES), true);
        if (is_array($tmp) and isset($tmp['dependencies'])) {
            $this->packages = $tmp['dependencies'];
        }
    }

    private function loadFile(string $path): string {
        assert(is_file($path), "Path $path not found");
        return file_get_contents($path);
    }

    public function execute(string $path, string $file, string $method): int {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        assert(is_dir($path), "Path $path not found");
        $path = realpath($path);
        $target = $path . $file;

        $args = [];
        $args[] = escapeshellarg($this->unityFile);
        $args[] = escapeshellarg($this->projectPath);
        $args[] = escapeshellarg($method);
        $args[] = escapeshellarg($target);
        $command = vsprintf('%s -quit -accept-apiupdate -batchmode -nographics -projectPath %s -executeMethod %s %s', $args);

        return CLI::execute($command);
    }

    public function runTests(string $resultsFile, string $testPlatform = 'EditMode') {
        // ..\2019.4.12f1\Editor\Unity.exe -runTests -batchmode -projectPath 2020WS.UnityPP.Lodil -testResults results.xml -testPlatform PlayMode
        $args = [];
        $args[] = escapeshellarg($this->unityFile);
        $args[] = escapeshellarg($this->projectPath);
        $args[] = escapeshellarg($resultsFile);
        $args[] = escapeshellarg($testPlatform);
        $command = vsprintf('%s -runTests -accept-apiupdate -batchmode -nographics -projectPath %s -testResults %s -testPlatform %s', $args);

        return CLI::execute($command);
    }

    public function getAssetFiles(): iterable {
        $path = $this->projectPath . DIRECTORY_SEPARATOR . 'Assets';
        $directory = new \RecursiveDirectoryIterator($path);
        $directoryIterator = new \RecursiveIteratorIterator($directory);
        foreach ($directoryIterator as $file) {
            if ($file->isFile()) {
                yield $file;
            }
        }
    }

    public function deleteFolder(string $folder) {
        $directory = new \SplFileInfo($this->projectPath . DIRECTORY_SEPARATOR . $folder);
        if ($directory->isDir()) {
            $this->rrmdir($directory->getRealPath());
        }
    }

    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && ! is_link($dir . "/" . $object)) {
                        rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
}

