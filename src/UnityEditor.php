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

    private bool $wasLicensed = false;

    public function isLicensed(string $projectPath): bool {
        if (! $this->isInstalled()) {
            return false;
        }
        if (! $this->wasLicensed) {
            $log = $this->execute(false, '-quit', '-projectPath', $projectPath)->getOutput();
            $this->wasLicensed = (strpos($log, self::LICENSE_SUCCESS_2020) !== false) or (strpos($log, self::LICENSE_SUCCESS_2019) !== false);
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
        assert(is_file($executable));
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
        foreach ($modules as $module) {
            $this->hub->installEditorModule($this, $module);
        }
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

    private function createProcess(array $arguments): Process {
        $arguments = array_merge([
            $this->executable,
            '-batchmode',
            '-nographics',
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