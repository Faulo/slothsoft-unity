<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Slothsoft\Core\FileSystem;
use Symfony\Component\Process\Process;

class UnityEditor {

    private const LICENSE_SUCCESS = '[Licensing::Module] Serial number assigned to:';

    private const LICENSE_CREATED = '[LicensingClient] Successfully processed ALF generation request:';

    /** @var UnityHub */
    public UnityHub $hub;

    /** @var string */
    public string $version;

    /** @var string */
    public ?string $executable = null;

    public function isInstalled(): bool {
        return is_string($this->executable) and is_file($this->executable);
    }

    public function isLicensed(string $projectPath): bool {
        if (! $this->isInstalled()) {
            return false;
        }
        $log = $this->execute('-quit', '-projectPath', $projectPath)->getOutput();
        return strpos($log, self::LICENSE_SUCCESS) !== false;
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
        return true;
    }

    public function license(string $projectPath): bool {
        foreach ($this->hub->findLicenses($this->version) as $licenseFile) {
            $result = $this->execute('-quit', '-manualLicenseFile', $licenseFile)->getExitCode();
            sleep(1);
            if ($result === 0 or $this->isLicensed($projectPath)) {
                return true;
            }
        }

        $log = $this->execute('-quit', '-createManualActivationFile')->getOutput();
        $match = [];
        if (preg_match('~(Unity_v[^\s]+\.alf)~', $log, $match)) {
            $log = trim($match[1]);
            if (is_file($log)) {
                $this->hub->prepareLicense($log);
            }
        }

        return false;
    }

    public function execute(string ...$arguments): Process {
        assert($this->isInstalled());

        $process = $this->createProcess($arguments);

        UnityHub::runUnityProcess($process);

        return $process;
    }

    private function createProcess(array $arguments): Process {
        $arguments = array_merge([
            $this->executable,
            '-batchmode',
            '-nographics',
            '-ignorecompilererrors',
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