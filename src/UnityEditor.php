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
    public ?string $executable = null;

    public function isInstalled(): bool {
        return is_string($this->executable) and is_file($this->executable);
    }

    private const ASSUME_LICENSE = true;

    private bool $wasLicensed = self::ASSUME_LICENSE;

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

    private const ARGUMENT_LICENSE_CREATE = '-createManualActivationFile';

    private const ARGUMENT_LICENSE_USE = '-manualLicenseFile';

    public function license(string $projectPath, $assumeSuccess = false): bool {
        foreach ($this->hub->findLicenses($this->version) as $licenseFile) {
            $result = $this->useLicenseFile($licenseFile);
            sleep(1);
            if ($result or $assumeSuccess or $this->isLicensed($projectPath)) {
                return true;
            }
        }

        return $this->tryToLicense();
    }

    private bool $hasTriedToLicense = false;

    private const LICENSE_IS_MISSING_2022 = 'No valid Unity Editor license found. Please activate your license.';

    private const LICENSE_IS_MISSING_6000 = 'Unity has not been activated with a valid License.';

    private static function isLicenseMissing(string $stdout): bool {
        return strpos($stdout, self::LICENSE_IS_MISSING_2022) !== false or strpos($stdout, self::LICENSE_IS_MISSING_6000) !== false;
    }

    private function tryToLicense(): bool {
        if (! $this->hasTriedToLicense) {
            $this->hasTriedToLicense = true;

            if ($file = $this->createLicenseFile()) {
                $this->hub->prepareLicense($file);

                if (UnityLicensor::hasCredentialsInEnvironment()) {
                    $licensor = new UnityLicensor();
                    $file = $licensor->sign($file);
                    $this->hub->prepareLicense($file);
                    return $this->useLicenseFile($file);
                }
            }
        }

        return false;
    }

    public function createLicenseFile(): ?string {
        $log = $this->execute(false, self::ARGUMENT_LICENSE_CREATE)->getOutput();
        $match = [];
        if (preg_match('~(Unity_v[^\s]+\.alf)~', $log, $match)) {
            $log = trim($match[1]);
            if (is_file($log)) {
                $file = temp_file(__CLASS__, 'ALF_');
                rename($log, $file);
                return realpath($file);
            }
        }

        return null;
    }

    public function useLicenseFile(string $file): bool {
        return $this->execute(false, self::ARGUMENT_LICENSE_USE, $file)->getExitCode() === 0;
    }

    private int $retryCount = 0;

    public function execute(bool $validateExitCode, string ...$arguments): Process {
        assert($this->isInstalled());

        try {
            $process = $this->createProcess($arguments);
            UnityHub::runUnityProcess($process, $validateExitCode);
        } catch (ExecutionError $error) {
            if (self::isLicenseMissing($error->getStdOut()) and $this->tryToLicense()) {
                return $this->execute($validateExitCode, ...$arguments);
            }

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

    const ENV_UNITY_ACCELERATOR_PARAMS = 'UNITY_ACCELERATOR_PARAMS';

    const ENV_UNITY_NO_GRAPHICS = 'UNITY_NO_GRAPHICS';

    private function createProcess(array $arguments): Process {
        $isLicenseRequest = (in_array(self::ARGUMENT_LICENSE_CREATE, $arguments) or in_array(self::ARGUMENT_LICENSE_USE, $arguments));

        if ($isLicenseRequest) {
            $arguments = array_merge([
                $this->executable,
                '-batchmode',
                '-logFile',
                '-'
            ], $arguments);
        } else {
            if ($endpoint = getenv(self::ENV_UNITY_ACCELERATOR_ENDPOINT)) {
                $params = getenv(self::ENV_UNITY_ACCELERATOR_PARAMS);
                $params = $params ? explode(' ', trim($params)) : [];

                $arguments = array_merge([
                    '-EnableCacheServer',
                    '-cacheServerEndpoint',
                    $endpoint,
                    '-cacheServerEnableDownload',
                    'true',
                    '-cacheServerEnableUpload',
                    'true'
                ], $params, $arguments);
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
                '-accept-apiupdate',
                '-timestamps'
            ], $arguments);
        }

        if (FileSystem::commandExists('xvfb-run')) {
            $arguments = array_merge([
                'xvfb-run',
                '-a'
            ], $arguments);
        }

        return new Process($arguments);
    }
}