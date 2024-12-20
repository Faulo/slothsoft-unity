<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;

class UnityEditor {

    private const LICENSE_SUCCESS_2020 = '[Licensing::Module] Serial number assigned to:';

    private const LICENSE_SUCCESS_2019 = 'Next license update check is after';

    /** @var UnityHub */
    public UnityHub $hub;

    /** @var string */
    public string $version;

    /** @var string */
    public ?string $changeset = null;

    /** @var string */
    public ?string $executable = null;

    public function isInstalled(): bool {
        return is_string($this->executable) and is_file($this->executable);
    }

    private bool $wasLicensed = false;

    public function isLicensed(string $projectPath): bool {
        if (! $this->isInstalled()) {
            return false;
        }
        if (! $this->wasLicensed) {
            $log = $this->execute(false, '-quit', '-projectPath', $projectPath)->getOutput();
            if (strpos($log, self::LICENSE_SUCCESS_2019) !== false) {
                $this->wasLicensed = true;
            }
            if (strpos($log, self::LICENSE_SUCCESS_2020) !== false) {
                $this->wasLicensed = true;
            }
        }
        return $this->wasLicensed;
    }

    public function __construct(UnityHub $hub, string $version) {
        $this->hub = $hub;
        $this->version = $version;
    }

    public function __toString(): string {
        return "Unity Editor v{$this->version}";
    }

    public function setExecutable(string $executable) {
        assert(is_file($executable), "Failed to find Unity Editor executable at '$executable'!");
        $this->executable = $executable;
    }

    public function install(): bool {
        $this->hub->installEditor($this);
        return $this->isInstalled();
    }

    public function installModules(string ...$modules): bool {
        if (! $this->isInstalled()) {
            return false;
        }

        $this->hub->installEditorModule($this, ...$modules);

        // Unity needs permission to access the installed files
        // https://stackoverflow.com/questions/61865817/jdk-directory-is-not-set-or-invalid-unity
        if (FileSystem::commandExists('chmod')) {
            exec('chmod -R 777 ' . escapeshellarg(dirname($this->executable)));
        }

        return true;
    }

    public function license(string $projectPath, $assumeSuccess = false): bool {
        foreach ($this->hub->findLicenses($this->version) as $licenseFile) {
            $result = $this->execute(false, '-quit', '-manualLicenseFile', $licenseFile)->getExitCode();
            sleep(1);
            if ($result === 0 or $assumeSuccess or $this->isLicensed($projectPath)) {
                return true;
            }
        }

        $log = $this->execute(false, '-quit', '-createManualActivationFile')->getOutput();
        $match = [];
        if (preg_match('~(Unity_v[^\s]+\.alf)~', $log, $match)) {
            $log = trim($match[1]);
            if (is_file($log)) {
                $this->hub->prepareLicense($log);
            }
        }

        return false;
    }

    private int $retryCount = 0;

    public function execute(bool $validateExitCode, string ...$arguments): Process {
        assert($this->isInstalled());

        try {
            $process = $this->createProcess($arguments);
            UnityHub::runUnityProcess($process, $validateExitCode);
        } catch (ExecutionError $error) {
            if ($error->getExitCode() === 199) {
                $this->retryCount ++;
                if ($this->retryCount < 3) {
                    return $this->execute($validateExitCode, ...$arguments);
                }
            }
            throw $error;
        }

        return $process;
    }

    public function createEmptyProject(string $path): UnityProject {
        $process = $this->execute(true, '-createProject', $path, '-quit');

        $project = $this->hub->findProject($path, true);

        if (! $project) {
            throw ExecutionError::Error('AssertProject', "Failed to create empty project at '$path'!", $process);
        }

        return $project;
    }

    const ENV_UNITY_ACCELERATOR_ENDPOINT = 'UNITY_ACCELERATOR_ENDPOINT';

    const ENV_UNITY_NO_GRAPHICS = 'UNITY_NO_GRAPHICS';

    private function createProcess(array $arguments): Process {
        if ($endpoint = getenv(self::ENV_UNITY_ACCELERATOR_ENDPOINT)) {
            $arguments = array_merge([
                '-EnableCacheServer',
                '-cacheServerEndpoint',
                $endpoint,
                '-cacheServerEnableDownload',
                'true',
                '-cacheServerEnableUpload',
                'true'
            ], $arguments);
        }

        if ((int) getenv(self::ENV_UNITY_NO_GRAPHICS)) {
            $arguments = array_merge([
                '-nographics'
            ], $arguments);
        } else {
            $arguments = array_merge([
                '-logFile',
                '-'
            ], $arguments);
        }

        $arguments = array_merge([
            $this->executable,
            '-batchmode',
            '-accept-apiupdate'
        ], $arguments);

        if (FileSystem::commandExists('xvfb-run')) {
            $arguments = array_merge([
                'xvfb-run',
                '-a'
            ], $arguments);
        }

        return new Process($arguments);
    }
}