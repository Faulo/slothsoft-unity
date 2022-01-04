<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\CLI;
use RuntimeException;

class UnityProject {

    public static function guessVersion(string $projectPath): string {
        assert(is_dir($projectPath));
        $projectPath = realpath($projectPath);
        $projectFile = $projectPath . self::FILE_VERSION;
        assert(is_file($projectFile));
        $unityVersion = file_get_contents($projectFile);
        $match = [];
        if (preg_match('~m_EditorVersion: (.+)~', $unityVersion, $match)) {
            return trim($match[1]);
        }
        throw new RuntimeException('Unable to determine EditorVersion!');
    }

    const FILE_VERSION = '/ProjectSettings/ProjectVersion.txt';

    const FILE_PACKAGES = '/Packages/packages-lock.json';

    private $projectPath;

    private $editor;

    public $packages = [];

    public function __construct(string $projectPath, UnityEditor $editor) {
        assert(is_dir($projectPath), "Path $projectPath not found");

        $this->editor = $editor;
        $this->projectPath = realpath($projectPath);
        $this->loadProject();
    }

    private function loadProject() {
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

