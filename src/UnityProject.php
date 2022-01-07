<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\CLI;

class UnityProject {

    private $info;

    private $editor;

    public function __construct(UnityProjectInfo $info, UnityEditor $editor) {
        $this->info = $info;
        $this->editor = $editor;
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

    /**
     *
     * @link https://docs.unity3d.com/Packages/com.unity.test-framework@1.1/manual/reference-command-line.html
     * @param string $resultsFile
     * @param string $testPlatform
     * @return iterable
     */
    public function executeTestRunner(string $resultsFile, string $testPlatform = 'EditMode'): iterable {
        // ..\2019.4.12f1\Editor\Unity.exe -runTests -batchmode -projectPath 2020WS.UnityPP.Lodil -testResults results.xml -testPlatform PlayMode
        $args = [];
        $args[] = $this->editor->executable;
        $args[] = '-runTests';
        $args[] = '-accept-apiupdate';
        $args[] = '-batchmode';
        $args[] = '-nographics';
        $args[] = '-projectPath';
        $args[] = $this->projectPath;
        $args[] = '-testResults';
        $args[] = $resultsFile;
        $args[] = '-testPlatform';
        $args[] = $testPlatform;
        $daemon = new DaemonClient(5050);
        return $daemon->call(json_encode($args));
    }
}

