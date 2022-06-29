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

    public static function setUseDaemon(bool $value): void {
        self::useDaemon()->setValue($value);
    }

    public static function getUseDaemon(): bool {
        return self::useDaemon()->getValue();
    }

    private static function hubLocator(): ConfigurationField {
        static $field;
        if ($field === null) {
            switch (PHP_OS) {
                case 'Linux':
                    $locator = new LocateHubFromCommand('xvfb-run unityhub');
                    break;
                case 'WINNT':
                    $locator = new LocateHubForWindows();
                    break;
                default:
                    $locator = null;
                    break;
            }
            $field = new ConfigurationField($locator);
        }
        return $field;
    }

    public static function setHubLocator(IHubLocator $value): void {
        self::hubLocator()->setValue($value);
    }

    public static function getHubLocator(): IHubLocator {
        return self::hubLocator()->getValue();
    }

    /** @var bool */
    public $isInstalled = false;

    /** @var string */
    public $hubFile = null;

    /** @var UnityEditor[] */
    private $editors = null;

    /** @var string */
    private $editorPath = null;

    /** @var string[] */
    private $changesets = null;

    /** @var DaemonClient */
    private $daemon = null;

    public function __construct() {
        if ($hubLocator = self::getHubLocator()) {
            $this->hubFile = $hubLocator->locate();
            $this->isInstalled = $hubLocator->exists();
        }

        if (self::getUseDaemon()) {
            $this->daemon = new DaemonClient(5050);
        }
    }

    /**
     *
     * @return UnityEditor[]
     */
    public function getEditors(): array {
        $this->loadEditors();
        return $this->editors;
    }

    private function loadEditors(): void {
        if ($this->editors === null) {
            $this->editors = [];
            foreach ($this->loadInstalledEditors() as $version => $path) {
                $this->editors[$version] = new UnityEditor($this, $version);
                $this->editors[$version]->setExecutable($path);
            }
        }
    }

    private function loadInstalledEditors(): iterable {
        $editorPaths = $this->executeNow([
            'editors',
            '--installed'
        ]);
        if (strlen($editorPaths)) {
            foreach (explode(PHP_EOL, $editorPaths) as $line) {
                $line = explode(', installed at', $line, 2);
                assert(count($line) === 2);
                $version = trim($line[0]);
                $path = trim($line[1]);
                yield $version => $path;
            }
        }
    }

    public function getEditorPath(): string {
        $this->loadEditorPath();
        return $this->editorPath;
    }

    private function loadEditorPath(): void {
        if ($this->editorPath === null) {
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
            $this->editors[$version] = new UnityEditor($this, $version);
        }
        return $this->editors[$version];
    }

    public function installEditor(UnityEditor $editor, string ...$modules): void {
        $arguments = $this->createEditorInstallation($editor->version, $modules);
        $this->executeNow($arguments);

        foreach ($this->loadInstalledEditors() as $version => $path) {
            if ($version === $editor->version) {
                $editor->setExecutable($path);
                break;
            }
        }
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
        assert($this->isInstalled);
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

    public function findProject(string $projectPath): ?UnityProject {
        if ($info = UnityProjectInfo::find($projectPath)) {
            $editor = $this->getEditorByVersion($info->editorVersion);
            return new UnityProject($info, $editor);
        }
        return null;
    }
}

