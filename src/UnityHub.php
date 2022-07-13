<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Storage;
use Slothsoft\Core\Calendar\Seconds;
use Slothsoft\Core\Configuration\ConfigurationField;
use Generator;

class UnityHub {

    public static function getInstance(): self {
        static $instance;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

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

    private static $licenseFolders = [];

    public static function addLicenseFolder(string $folder): void {
        if (! is_dir($folder)) {
            throw new \InvalidArgumentException("Folder '$folder' does not exist!");
        }
        self::$licenseFolders[] = $folder;
    }

    private static function loggingEnabled(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(false);
        }
        return $field;
    }

    public static function setLoggingEnabled(bool $value): void {
        self::loggingEnabled()->setValue($value);
    }

    public static function getLoggingEnabled(): bool {
        return self::loggingEnabled()->getValue();
    }

    private static function hubLocator(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(self::inventHubLocator());
        }
        return $field;
    }

    private static function inventHubLocator(): HubLocatorInterface {
        if (PHP_OS === 'WINNT') {
            return new LocateHubFromWindowsRegistry([
                '--',
                '--headless'
            ]);
        }
        if (FileSystem::commandExists('xvfb-run') and FileSystem::commandExists('unityhub')) {
            return new LocateHubFromCommand([
                'xvfb-run',
                '-a',
                'unityhub',
                '--no-sandbox',
                '--headless'
            ]);
        }
        return new LocateHubNull();
    }

    public static function setHubLocator(HubLocatorInterface $value): void {
        self::hubLocator()->setValue($value);
    }

    public static function getHubLocator(): HubLocatorInterface {
        return self::hubLocator()->getValue();
    }

    /** @var bool */
    public function isInstalled() {
        return self::getHubLocator()->exists();
    }

    /** @var UnityEditor[] */
    private ?array $editors = null;

    /** @var string */
    private string $editorPath = '';

    /** @var string[] */
    private ?array $changesets = null;

    /** @var DaemonClient */
    private ?DaemonClient $daemon = null;

    private function __construct() {
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
        $editorPaths = $this->execute([
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
        if ($this->editorPath === '') {
            if ($path = $this->execute([
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
        $this->execute($arguments);

        foreach ($this->loadInstalledEditors() as $version => $path) {
            if ($version === $editor->version) {
                $editor->setExecutable($path);
                break;
            }
        }
    }

    public function installEditorModule(UnityEditor $editor, string ...$modules): void {
        $arguments = $this->createModuleInstallation($editor->version, $modules);
        $this->execute($arguments);
    }

    public function findLicenses(string $editorVersion): iterable {
        foreach (self::$licenseFolders as $folder) {
            foreach (FileSystem::scanDir($folder, FileSystem::SCANDIR_EXCLUDE_DIRS | FileSystem::SCANDIR_REALPATH) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) === 'ulf') {
                    if ($document = DOMHelper::loadDocument($file) and $xpath = DOMHelper::loadXPath($document)) {
                        if ($licenseVersion = $xpath->evaluate('string(//ClientProvidedVersion/@Value)')) {
                            if (substr($licenseVersion, 0, 4) === substr($editorVersion, 0, 4)) {
                                yield $file;
                            }
                        }
                    }
                }
            }
        }
    }

    public function prepareLicense(string $licenseFile): void {
        assert(is_file($licenseFile));
        foreach (self::$licenseFolders as $folder) {
            $targetFile = $folder . DIRECTORY_SEPARATOR . basename($licenseFile);
            copy($licenseFile, $targetFile);
            chmod($targetFile, 0777);
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

    public function execute(array $arguments): string {
        return $this->createProcessRunner($arguments)->toString();
    }

    public function executeStream(array $arguments): Generator {
        return $this->createProcessRunner($arguments)->toGenerator();
    }

    private function createProcessRunner(array $arguments): ProcessRunner {
        assert($this->isInstalled());

        $process = self::getHubLocator()->create($arguments);

        $runner = new ProcessRunner($process, self::getLoggingEnabled());

        return $runner;
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

