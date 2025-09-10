<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\DOMHelper;
use Slothsoft\Core\FileSystem;
use Slothsoft\Core\Configuration\ConfigurationField;
use Symfony\Component\Process\Process;
use InvalidArgumentException;
use Throwable;
use Slothsoft\Core\ServerEnvironment;

class UnityHub {

    private const UNITY_VERSION_HISTORY = 'https://symbolserver.unity3d.com/000Admin/history.txt';

    private const USE_UNITY_ARCHIVE = false;

    private const UNITY_ARCHIVE_ALL = 'https://unity.com/releases/editor/archive';

    private const UNITY_ARCHIVE_FINAL = 'https://unity.com/releases/editor/whats-new/';

    private const UNITY_ARCHIVE_BETA = 'https://unity.com/releases/editor/beta/';

    private const UNITY_ARCHIVE_ALPHA = 'https://unity.com/releases/editor/alpha/';

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
        if (FileSystem::commandExists('unityhub')) {
            if (FileSystem::commandExists('xvfb-run')) {
                return new LocateHubFromCommand([
                    'xvfb-run',
                    '-a',
                    'unityhub',
                    '--no-sandbox',
                    '--headless'
                ]);
            }

            return new LocateHubFromCommand([
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

    private bool $hasLoadedEditorsFromCache = false;

    /** @var string */
    private string $editorPath = '';

    /** @var string[] */
    private ?array $changesets = null;

    /** @var resource */
    private $fileContext = null;

    private function getFileContext() {
        if ($this->fileContext === null) {
            $this->fileContext = stream_context_create([
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ]);
        }

        return $this->fileContext;
    }

    /**
     *
     * @return UnityEditor[]
     */
    public function getEditors(): array {
        $this->loadEditors(true);
        return $this->editors;
    }

    private function loadEditors(bool $allowCache): void {
        if ($this->editors === null) {
            $this->editors = [];
            foreach ($this->loadInstalledEditors($allowCache) as $version => $path) {
                if (is_file($path)) {
                    $this->editors[$version] = new UnityEditor($this, $version);
                    $this->editors[$version]->setExecutable($path);
                } else {
                    if ($this->hasLoadedEditorsFromCache) {
                        // cache appears to be stale, let's try again
                        $this->editors = null;
                        $this->loadEditors(false);
                        return;
                    }
                }
            }
        }
    }

    private function loadInstalledEditors(bool $allowCache): iterable {
        $this->hasLoadedEditorsFromCache = ($allowCache and ($editorPaths = $this->loadInstalledEditorsCache()));

        if ($this->hasLoadedEditorsFromCache) {
            if (self::getLoggingEnabled()) {
                fwrite(STDERR, self::editorPathCache() . PHP_EOL);
                fwrite(STDERR, $editorPaths . PHP_EOL);
            }
        } else {
            $editorPaths = trim($this->execute('editors', '--installed')->getOutput());
            $this->saveInstalledEditorsCache($editorPaths);
        }

        if (strlen($editorPaths)) {
            foreach (explode("\n", $editorPaths) as $line) {
                $line = explode('installed at', str_replace(',', '', $line), 2);
                if (count($line) === 2) {
                    $version = trim($line[0]);
                    $path = trim($line[1]);
                    if (strlen($version) and strlen($path)) {
                        yield $version => $path;
                    }
                }
            }
        }
    }

    private const EDITOR_PATH_CACHE = 'unity-editors-installed.tmp';

    private static function editorPathCache(): string {
        return ServerEnvironment::getCacheDirectory() . DIRECTORY_SEPARATOR . self::EDITOR_PATH_CACHE;
    }

    private function loadInstalledEditorsCache(): ?string {
        return is_file(self::editorPathCache()) ? file_get_contents(self::editorPathCache()) : null;
    }

    private function saveInstalledEditorsCache(string $data): void {
        if (is_dir(ServerEnvironment::getCacheDirectory())) {
            file_put_contents(self::editorPathCache(), $data);
            chmod(self::editorPathCache(), 0777);
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
        $this->loadEditors(true);
        if (! isset($this->editors[$version]) and $this->hasLoadedEditorsFromCache) {
            $this->editors = null;
            $this->loadEditors(false);
        }

        if (! isset($this->editors[$version])) {
            $this->editors[$version] = new UnityEditor($this, $version);
        }

        return $this->editors[$version];
    }

    public function installEditor(UnityEditor $editor, string ...$modules): void {
        $arguments = $this->createEditorInstallation($editor->version, $modules);
        $this->execute(...$arguments);

        foreach ($this->loadInstalledEditors(false) as $version => $path) {
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
            $version = explode('.', trim($minVersion));
            if (count($version) <= 3) {
                $version[1] ??= '0';
                $version[2] ??= '0f1';
                $maxVersion = implode('.', $version);
            }
        }
        if ($maxVersion === null) {
            throw ExecutionError::Error('AssertEditorVersion', "Failed to find editor that satisfies mininum version requirement '$minVersion'!");
        }
        return $maxVersion;
    }

    private array $customChangesets = [];

    public function registerChangeset(string $version, string $changeset): void {
        $this->customChangesets[$version] = $changeset;
    }

    private function inventChangeset(string $version): string {
        if (isset($this->customChangesets[$version])) {
            return $this->customChangesets[$version];
        }

        $this->loadChangesets();

        if (isset($this->changesets[$version])) {
            return $this->changesets[$version];
        }

        if (strpos($version, 'f') !== false) {
            $this->loadChangesetsFromUrl(self::UNITY_ARCHIVE_FINAL . preg_replace('~f.*~', '', $version));

            if (isset($this->changesets[$version])) {
                return $this->changesets[$version];
            }
        }

        if (strpos($version, 'b') !== false) {
            $this->loadChangesetsFromUrl(self::UNITY_ARCHIVE_BETA . $version);

            if (isset($this->changesets[$version])) {
                return $this->changesets[$version];
            }
        }

        if (strpos($version, 'b') !== false) {
            $this->loadChangesetsFromUrl(self::UNITY_ARCHIVE_BETA . $version);

            if (isset($this->changesets[$version])) {
                return $this->changesets[$version];
            }
        }

        if (strpos($version, 'a') !== false) {
            $this->loadChangesetsFromUrl(self::UNITY_ARCHIVE_ALPHA . $version);

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

    private function loadChangesets(): void {
        if ($this->changesets === null) {
            $this->changesets = [];

            $this->loadVersionsFromUrl(self::UNITY_VERSION_HISTORY);

            if (self::USE_UNITY_ARCHIVE) {
                $this->loadChangesetsFromUrl(self::UNITY_ARCHIVE_ALL);
            }
        }
    }

    private function loadChangesetsFromUrl(string $url): void {
        if ($html = file_get_contents($url, false, $this->getFileContext())) {
            $matches = [];
            $count = preg_match_all('~unityhub://([^/]+)/([a-f0-9]{12})~', $html, $matches, PREG_SET_ORDER);
            if ($count) {
                foreach ($matches as $match) {
                    $version = $match[1];
                    $changeset = $match[2];
                    $this->changesets[$version] = $changeset;
                }
                return;
            }
        }

        // legacy website
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

    private function loadVersionsFromUrl(string $url): void {
        if (($handle = fopen($url, "r", false, $this->getFileContext())) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $version = $data[6] ?? '';
                if ($version !== '') {
                    $this->changesets[$version] = null;
                }
            }
            fclose($handle);
        }
    }

    public function findProject(string $projectPath, bool $includeSubdirectories = false): ?UnityProject {
        if ($info = UnityProjectInfo::find($projectPath, $includeSubdirectories)) {
            $this->registerChangeset($info->editorVersion, $info->editorChangeset);
            return new UnityProject($info, $this);
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

