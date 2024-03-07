<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Configuration\ConfigurationField;
use Symfony\Component\Process\Process;
use InvalidArgumentException;
use Throwable;

class UnityHub {

    public static function getInstance(): self {
        static $instance;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    private static $licenseFolders = [];

    public static function addLicenseFolder(string $folder): void {
        if (! is_dir($folder)) {
            throw new InvalidArgumentException("Folder '$folder' does not exist!");
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

    private static function throwOnFailure(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(false);
        }
        return $field;
    }

    public static function setThrowOnFailure(bool $value): void {
        self::throwOnFailure()->setValue($value);
    }

    public static function getThrowOnFailure(): bool {
        return self::throwOnFailure()->getValue();
    }

    private static function processTimeout(): ConfigurationField {
        static $field;
        if ($field === null) {
            $field = new ConfigurationField(0);
        }
        return $field;
    }

    public static function setProcessTimeout(int $value): void {
        self::processTimeout()->setValue($value);
    }

    public static function getProcessTimeout(): int {
        return self::processTimeout()->getValue();
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
        $editorPaths = trim($this->execute('editors', '--installed')->getOutput());
        if (strlen($editorPaths)) {
            foreach (explode("\n", $editorPaths) as $line) {
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
            $result = $this->execute('install-path', '--get');
            if ($path = realpath(trim($result->getOutput()))) {
                $this->editorPath = $path;
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
        $this->execute(...$arguments);

        foreach ($this->loadInstalledEditors() as $version => $path) {
            if ($version === $editor->version) {
                $editor->setExecutable($path);
                break;
            }
        }
    }

    public function installEditorModule(UnityEditor $editor, string ...$modules): void {
        $arguments = $this->createModuleInstallation($editor->version, $modules);
        $this->execute(...$arguments);
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

        $changeset = $this->inventChangeset($version);

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

    public function inventStableEditorVersion(string $minVersion): string {
        $this->loadChangesets();
        $maxVersion = null;
        foreach (array_keys($this->changesets) as $version) {
            if (version_compare($version, $minVersion, '>=')) {
                if ($maxVersion === null or version_compare($version, $maxVersion, '<')) {
                    $maxVersion = $version;
                }
            }
        }
        if ($maxVersion === null) {
            throw ExecutionError::Error('AssertEditorVersion', "Failed to find editor that satisfies mininum version requirement '$minVersion'!");
        }
        return $maxVersion;
    }

    private function inventChangeset(string $version): string {
        $this->loadChangesets();

        if (isset($this->changesets[$version])) {
            return $this->changesets[$version];
        }

        if (strpos($version, 'b') !== false) {
            $this->loadChangesetsFromUrl('https://unity3d.com/unity/beta/' . $version);

            if (isset($this->changesets[$version])) {
                return $this->changesets[$version];
            }
        }

        if (strpos($version, 'a') !== false) {
            $this->loadChangesetsFromUrl('https://unity3d.com/unity/alpha/' . $version);

            if (isset($this->changesets[$version])) {
                return $this->changesets[$version];
            }
        }

        throw ExecutionError::Error('AssertEditorChangeset', "Failed to determine changeset ID for Unity version '{$version}'!");
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

    public function execute(string ...$arguments): Process {
        assert($this->isInstalled());

        $process = self::getHubLocator()->create($arguments);

        self::runUnityProcess($process, false);

        return $process;
    }

    public static function runUnityProcess(Process $process, bool $validateExitCode = true): void {
        if (self::getLoggingEnabled()) {
            fwrite(STDERR, $process->getCommandLine() . PHP_EOL);
        }

        $process->setTimeout(self::getProcessTimeout());

        try {
            $process->run(function (string $type, string $data): void {
                if (self::getLoggingEnabled() or $type === Process::ERR) {
                    fwrite(STDERR, $data);
                }
            });
        } catch (Throwable $e) {
            throw ExecutionError::Exception($e, $process);
        }

        if ($validateExitCode and $process->getExitCode() !== 0) {
            $code = json_encode($process->getExitCode());
            $text = $process->getExitCodeText();
            throw ExecutionError::Error("AssertExitCode", "Process finished with exit code '$code': $text.", $process);
        }
    }

    private function scanForSubDirectories(string $directory): iterable {
        $options = FileSystem::SCANDIR_SORT | FileSystem::SCANDIR_EXCLUDE_FILES;
        return FileSystem::scanDir($directory, $options);
    }

    const CHANGESET_URL = 'http://unity3d.com/get-unity/download/archive';

    private function loadChangesets(): void {
        if ($this->changesets === null) {
            $this->changesets = [];
            $this->loadChangesetsFromUrl(self::CHANGESET_URL);
        }
    }

    private function loadChangesetsFromUrl(string $url): void {
        if ($document = @DOMHelper::loadDocument($url, true)) {
            $xpath = DOMHelper::loadXPath($document);
            foreach ($xpath->evaluate('//a[starts-with(@href, "unityhub")]') as $node) {
                // unityhub://2019.4.17f1/667c8606c536
                $href = $node->getAttribute('href');
                $version = parse_url($href, PHP_URL_HOST);
                $changeset = parse_url($href, PHP_URL_PATH);

                $this->changesets[$version] = substr($changeset, 1);
            }
        }
    }

    public function findProject(string $projectPath, bool $includeSubdirectories = false): ?UnityProject {
        if ($info = UnityProjectInfo::find($projectPath, $includeSubdirectories)) {
            $editor = $this->getEditorByVersion($info->editorVersion);
            return new UnityProject($info, $editor);
        }
        return null;
    }

    public function findPackage(string $projectPath, bool $includeSubdirectories = false): ?UnityPackage {
        if ($info = UnityPackageInfo::find($projectPath, $includeSubdirectories)) {
            $editorVersion = $this->inventStableEditorVersion($info->getMinEditorVersion());
            $editor = $this->getEditorByVersion($editorVersion);
            return new UnityPackage($info, $editor);
        }
        return null;
    }
}

