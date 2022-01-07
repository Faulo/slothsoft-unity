<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Storage;
use Slothsoft\Core\Calendar\Seconds;
use Slothsoft\Core\Configuration\ConfigurationField;
use Symfony\Component\Process\Process;
use Generator;

class UnityHub {

    private static function useDaemon(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(false);
        }
        return $field;
    }

    public static function setUseDaemon(bool $value) {
        self::useDaemon()->setValue($value);
    }

    public static function getUseDaemon(): bool {
        return self::useDaemon()->getValue();
    }

    private static function getHubLocation() {
        $locator = new UnityHubLocator();
        return $locator->findHubLocation();
    }

    public $isInstalled = false;

    public $hubFile = null;

    private $editors = null;

    private $editorPath = null;

    private $changesets = null;

    private $daemon = null;

    public function __construct() {
        if ($hubFile = self::getHubLocation()) {
            $this->hubFile = $hubFile;
            if ($hubFile = realpath($hubFile)) {
                $this->hubFile = $hubFile;
                $this->isInstalled = true;
            }
        }

        if (self::getUseDaemon()) {
            $this->daemon = new DaemonClient(5050);
        }
    }

    public function getEditors(): array {
        $this->loadEditors();
        return $this->editors;
    }

    private function loadEditors(): void {
        if ($this->editors === null) {
            assert($this->isInstalled);

            $this->editors = [];
            $editorPaths = $this->executeNow([
                'editors',
                '--installed'
            ]);
            foreach (explode(PHP_EOL, $editorPaths) as $line) {
                $line = explode(', installed at', $line, 2);
                assert(count($line) === 2);
                $version = trim($line[0]);
                $path = trim($line[1]);
                $this->editors[$version] = new UnityEditor($this, $path, $version);
            }
        }
    }

    public function getEditorPath(): string {
        $this->loadEditorPath();
        return $this->editorPath;
    }

    private function loadEditorPath(): void {
        if ($this->editorPath === null) {
            assert($this->isInstalled);

            if ($path = $this->executeNow([
                'install-path',
                '--get'
            ])) {
                if ($path = realpath($path)) {
                    $this->editorPath = $path;
                }
            }
        }
    }

    public function getEditorByVersion(string $version): UnityEditor {
        $this->loadEditors();
        if (! isset($this->editors[$version])) {
            $this->loadEditorPath();
            if ($this->editorPath === null) {
                throw new \RuntimeException("Failed to determine editor path!");
            }
            $this->editors[$version] = new UnityEditor($this, $this->editorPath . DIRECTORY_SEPARATOR . $version, $version);
        }
        return $this->editors[$version];
    }

    public function createEditorInstallation(string $version, array $modules = []): array {
        assert($version !== '');
        $this->loadChangesets();
        assert(isset($this->changesets[$version]));
        $changeset = $this->changesets[$version];
        $args = [
            'install',
            '--version',
            $version,
            '--changeset',
            $changeset,
            '--childModules'
        ];
        foreach ($modules as $module) {
            $args[] = '--module';
            $args[] = $module;
        }
        return $args;
    }

    public function createModuleInstallation(string $version, array $modules = []): array {
        assert($version !== '');
        $args = [
            'install-modules',
            '--version',
            $version,
            '--childModules'
        ];
        foreach ($modules as $module) {
            $args[] = '--module';
            $args[] = $module;
        }
        return $args;
    }

    public function executeNow(array $arguments): string {
        $result = '';
        foreach ($this->executeStream($arguments) as $value) {
            $result .= $value;
        }
        return trim($result);
    }

    public function executeStream(array $arguments): Generator {
        $arguments = array_merge([
            $this->hubFile,
            '--',
            '--headless'
        ], $arguments);
        if ($this->daemon) {
            yield from $this->daemon->call(json_encode($arguments));
        } else {
            $process = new Process($arguments);
            $process->setTimeout(0);
            $process->start();
            foreach ($process as $type => $data) {
                if ($type === $process::OUT) {
                    yield $data;
                }
            }
        }
    }

    private function scanForSubDirectories(string $directory): iterable {
        $options = FileSystem::SCANDIR_SORT | FileSystem::SCANDIR_EXCLUDE_FILES;
        return FileSystem::scanDir($directory, $options);
    }

    const CHANGESET_URL = 'https://unity3d.com/get-unity/download/archive';

    private function loadChangesets() {
        if ($this->changesets === null) {
            assert($this->isInstalled);

            $this->changesets = [];
            if ($xpath = Storage::loadExternalXPath(self::CHANGESET_URL, Seconds::DAY)) {
                foreach ($xpath->evaluate('//a[starts-with(@href, "unityhub")]') as $node) {
                    // unityhub://2019.4.17f1/667c8606c536
                    $href = $node->getAttribute('href');
                    $version = parse_url($href, PHP_URL_HOST);
                    $changeset = parse_url($href, PHP_URL_PATH);
                    assert(! isset($this->changesets[$version]));
                    $this->changesets[$version] = substr($changeset, 1);
                }
            }
        }
    }

    public function findProject(string $projectPath): UnityProject {
        $info = UnityProjectInfo::find($projectPath);
        $editor = $this->getEditorByVersion($info->editorVersion);
        return new UnityProject($info, $editor);
    }
}

