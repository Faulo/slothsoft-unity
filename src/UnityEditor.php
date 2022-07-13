<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;
use Generator;

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

    public function isLicensed(): bool {
        if (! $this->isInstalled()) {
            return false;
        }
        $log = $this->execute([]);
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

    public function license(): bool {
        foreach ($this->hub->findLicenses($this->version) as $licenseFile) {
            $this->execute([
                '-manualLicenseFile',
                $licenseFile
            ]);
            if ($this->isLicensed()) {
                return true;
            }
        }

        $log = $this->execute([
            '-createManualActivationFile'
        ]);
        $position = strpos($log, self::LICENSE_CREATED);
        if ($position !== false) {
            $log = explode("\n", substr($log, $position + strlen(self::LICENSE_CREATED)), 2);
            $log = trim($log[0]);
            if (is_file($log)) {
                $this->hub->prepareLicense($log);
            }
        }

        return false;
    }

    public function execute(array $arguments): string {
        return $this->createProcessRunner($arguments)->toString();
    }

    public function executeStream(array $arguments): Generator {
        return $this->createProcessRunner($arguments)->toGenerator();
    }

    private function createProcessRunner(array $arguments): ProcessRunner {
        assert($this->isInstalled());

        $process = $this->createProcess($arguments);

        $runner = new ProcessRunner($process, self::getLoggingEnabled());

        return $runner;
    }

    private function createProcess(array $arguments): Process {
        $command = array_merge([
            $this->executable,
            '-quit',
            '-batchmode',
            '-nographics',
            '-ignorecompilererrors',
            '-accept-apiupdate'
        ], $arguments);
        return new Process($command);
    }
}