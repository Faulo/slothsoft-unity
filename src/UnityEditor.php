<?php
declare(strict_types = 1);
namespace Slothsoft\Unity;

use Symfony\Component\Process\Process;

class UnityEditor {

    /** @var UnityHub */
    public $hub;

    /** @var string */
    public $version;

    /** @var string */
    public $executable;

    public function isInstalled(): bool {
        return is_string($this->executable) and is_file($this->executable);
    }

    public function isLicensed(): bool {
        return false;
    }

    public function __construct(UnityHub $hub, string $version) {
        $this->hub = $hub;
        $this->version = $version;
    }

    public function setExecutable(string $executable) {
        assert(is_file($executable));
        $this->executable = $executable;
    }

    public function install(): bool {
        $this->hub->installEditor($this);
        return $this->isInstalled();
    }

    public function license(): bool {
        foreach ($this->hub->findLicenses($this) as $licenseFile) {
            $this->execute([
                '-manualLicenseFile',
                $licenseFile
            ]);
            return true;
        }
        return true;
    }

    public function execute(array $arguments): string {
        $command = array_merge([
            $this->executable,
            '-quit',
            '-batchmode',
            '-nographics',
            '-accept-apiupdate'
        ], $arguments);
        $process = new Process($command);
        $process->setTimeout(0);
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
}