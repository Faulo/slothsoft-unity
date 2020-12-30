<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Storage;
use Slothsoft\Core\Calendar\Seconds;
use Slothsoft\Core\Configuration\ConfigurationField;
use Generator;

class UnityHub {

    const DEFAULT_HUB_LOCATION = 'C:/Unity/Unity Hub/Unity Hub.exe';

    const DEFAULT_EDITOR_LOCATION = 'C:/Unity';

    const DEFAULT_WORKSPACE_LOCATION = 'C:/Unity/workspace';

    private static function hubLocation(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(self::DEFAULT_HUB_LOCATION);
        }
        return $field;
    }

    public static function setHubLocation(string $value) {
        self::hubLocation()->setValue($value);
    }

    public static function getHubLocation(): string {
        return self::hubLocation()->getValue();
    }

    private static function workspaceLocation(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(self::DEFAULT_WORKSPACE_LOCATION);
        }
        return $field;
    }

    public static function setWorkspaceLocation(string $value) {
        self::workspaceLocation()->setValue($value);
    }

    public static function getWorkspaceLocation(): string {
        return self::workspaceLocation()->getValue();
    }

    public $isInstalled;

    public $hubFile;

    public $workspaceDirectory;

    public $projects = [];

    public $editors;

    public $daemon;

    public function __construct() {
        $this->hubFile = realpath(self::getHubLocation());
        $this->workspaceDirectory = realpath(self::getWorkspaceLocation());

        $this->isInstalled = ($this->hubFile and $this->workspaceDirectory);
        $this->daemon = new DaemonClient(5050);
    }

    public function loadEditors() {
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
            $this->editors[$version] = new UnityEditor($path, $version);
        }
    }

    public function createEditorInstallation(string $version, array $modules = []): array {
        assert($version !== '');
        $this->loadChangesets();
        $changeset = $this->changesets[$version] ?? '';
        assert($changeset !== '');
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

    public function getProjectPath(string $id, string $branch): string {
        assert($id !== '');
        assert($branch !== '');
        return $this->workspaceDirectory . DIRECTORY_SEPARATOR . $id . '.' . $branch;
    }

    public function loadProject(string $projectPath): UnityProject {
        assert($this->isInstalled);
        $version = UnityProject::guessVersion($projectPath);
        $this->loadEditors();
        assert(isset($this->editors[$version]));
        return new UnityProject($projectPath, $this->editors[$version]);
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
        yield from $this->daemon->call(json_encode($arguments));
    }

    private function scanForSubDirectories(string $directory): iterable {
        $options = FileSystem::SCANDIR_SORT | FileSystem::SCANDIR_EXCLUDE_FILES;
        return FileSystem::scanDir($directory, $options);
    }

    private $changesets;

    const CHANGESET_URL = 'https://unity3d.com/get-unity/download/archive';

    private function loadChangesets() {
        $this->changesets = [];
        if ($xpath = Storage::loadExternalXPath(self::CHANGESET_URL, Seconds::DAY)) {
            foreach ($xpath->evaluate('//a[starts-with(@href, "unityhub")]') as $node) {
                ;
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

