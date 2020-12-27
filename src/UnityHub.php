<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\Configuration\ConfigurationField;
use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;

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

    public function __construct() {
        $this->hubFile = realpath(self::getHubLocation());
        $this->workspaceDirectory = realpath(self::getWorkspaceLocation());

        $this->isInstalled = ($this->hubFile and $this->workspaceDirectory);
    }

    public function loadEditors() {
        assert($this->isInstalled);
        $this->editors = [];
        $editorPaths = $this->execute([
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
    public function createEditorListing(): Process {
        $args = ['editors', '-r'];
        $process = $this->createProcess($args);
        return $process;
    }
    public function createEditorInstallation(string $version, array $modules = []): Process {
        assert($version !== '');
        $args = ['install', '--version', $version, '--childModules'];
        foreach ($modules as $module) {
            $args[] = '--module';
            $args[] = $module;
        }
        $process = $this->createProcess($args);
        return $process;
    }
    public function createModuleInstallation(string $version, array $modules = []): Process {
        assert($version !== '');
        $args = ['install-modules', '--version', $version, '--childModules'];
        foreach ($modules as $module) {
            $args[] = '--module';
            $args[] = $module;
        }
        $process = $this->createProcess($args);
        return $process;
    }

    public function loadProjects() {
        assert($this->isInstalled);
        foreach ($this->scanForSubDirectories($this->workspaceDirectory) as $project) {
            var_dump($project);
        }
    }

    public function execute(array $arguments): string {
        $process = $this->createProcess($arguments);
        $process->start();
        $result = '';
        foreach ($process as $type => $data) {
            if ($type === $process::OUT) {
                $result .= $data;
            } else {
                fwrite(STDERR, $data);
            }
        }
        return trim($result);
    }
    
    public function createProcess(array $arguments) : Process {
        assert($this->isInstalled);
        
        $command = array_merge([
            $this->hubFile,
            '--',
            '--headless'
        ], $arguments);
        $process = new Process($command);
        $process->setTimeout(0);
        return $process;
    }

    private function scanForSubDirectories(string $directory): iterable {
        $options = FileSystem::SCANDIR_SORT | FileSystem::SCANDIR_EXCLUDE_FILES;
        return FileSystem::scanDir($directory, $options);
    }
}

